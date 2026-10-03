<?php
declare(strict_types=1);

/**
 * SQLite-backed store for E-01 (Session), E-02 (Exchange), E-03 (rate-limit window).
 * Per AP-04: one file, no external DB. Per plan §7: SQLite over a JSON file because
 * PDO/SQLite3 is core PHP and needs no flock()+read-modify-write per turn.
 */
final class Store
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA journal_mode = WAL'); // one writer at a time is fine, but avoid readers blocking on it

        $this->migrate();
    }

    private function migrate(): void
    {
        // displayable defaults to 0 now: nothing is projected until the visitor
        // explicitly opts in via projection_consent (see recordConsentDecision).
        // Pilot import still forces it true directly (setDisplayable) — that path
        // has no visitor to ask.
        // One DDL, two uses: first-run CREATE and the origin-CHECK rebuild below.
        $sessionsDdl = fn(string $table): string => <<<SQL
            CREATE TABLE $table (
                id                  TEXT PRIMARY KEY,
                created_at          TEXT NOT NULL,
                scenario_source     TEXT CHECK (scenario_source IN ('first-contribution', 'generated')),
                origin              TEXT NOT NULL CHECK (origin IN ('pilot', 'live', 'study')),
                scenario            TEXT,
                title               TEXT,
                displayable         INTEGER NOT NULL DEFAULT 0,
                consent_granted     INTEGER,
                tos_agreed          INTEGER,
                projection_consent  INTEGER,
                turn_count          INTEGER NOT NULL DEFAULT 0,
                bonus_turns         INTEGER NOT NULL DEFAULT 0,
                last_active_at      TEXT NOT NULL
            )
        SQL;
        $sessionsExist = $this->pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'sessions'")->fetchColumn();
        if (!$sessionsExist) {
            $this->pdo->exec($sessionsDdl('sessions'));
        }

        // Guard for a store.db created before tos_agreed/projection_consent/title/
        // bonus_turns existed — SQLite has no "ADD COLUMN IF NOT EXISTS" on the versions this targets.
        $existing = array_column($this->pdo->query('PRAGMA table_info(sessions)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach (['tos_agreed' => 'INTEGER', 'projection_consent' => 'INTEGER', 'title' => 'TEXT', 'bonus_turns' => 'INTEGER NOT NULL DEFAULT 0'] as $column => $type) {
            if (!in_array($column, $existing, true)) {
                $this->pdo->exec("ALTER TABLE sessions ADD COLUMN $column $type");
            }
        }

        // A store.db created before the 'study' origin (ADR 0019) has CHECK
        // (origin IN ('pilot', 'live')), and SQLite can't alter a CHECK in place:
        // rebuild the table. Foreign keys go OFF around it (the pragma is a no-op
        // inside a transaction) because DROP TABLE would otherwise cascade-delete
        // session_evaluations and trip the exchanges FK. Columns are named
        // explicitly since ALTER-added columns sit in a different order on old files.
        $sessionsSql = (string) $this->pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'sessions'")->fetchColumn();
        if (!str_contains($sessionsSql, "'study'")) {
            $columns = 'id, created_at, scenario_source, origin, scenario, title, displayable, consent_granted, tos_agreed, projection_consent, turn_count, bonus_turns, last_active_at';
            $this->pdo->exec('PRAGMA foreign_keys = OFF');
            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec($sessionsDdl('sessions_new'));
                $this->pdo->exec("INSERT INTO sessions_new ($columns) SELECT $columns FROM sessions");
                $this->pdo->exec('DROP TABLE sessions');
                $this->pdo->exec('ALTER TABLE sessions_new RENAME TO sessions');
                $this->pdo->commit();
            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            } finally {
                $this->pdo->exec('PRAGMA foreign_keys = ON');
            }
        }

        // ponytail: one row per exchange (contribution + response), not the more
        // typical one-row-per-message shape (role-tagged messages table). This is
        // on purpose (spec E-02, L3-SE-03-backend-service.md) and safe specifically
        // because the project's scope ends with the exhibition: no streaming, no
        // edit/regenerate, no per-message metadata anywhere in this codebase. If it
        // ever outlives the exhibition and needs any of those, this table is the
        // thing to replace with a messages table — nothing outside this file reads
        // the raw row shape (everything consumes hydrateExchange()'s pair), so the
        // change stays contained to this class plus a one-time data migration.
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS exchanges (
                id                   INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id           TEXT NOT NULL REFERENCES sessions(id),
                visitor_contribution TEXT NOT NULL,
                sparring_response    TEXT NOT NULL,
                position             INTEGER NOT NULL,
                created_at           TEXT NOT NULL
            )
        SQL);
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_exchanges_session ON exchanges(session_id)');

        // Guard for a store.db created before reply_count existed — same
        // ALTER-if-missing idiom as the sessions table above. Non-null
        // default (0) makes the bare ADD COLUMN valid with no backfill.
        $existingExchangeColumns = array_column($this->pdo->query('PRAGMA table_info(exchanges)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('reply_count', $existingExchangeColumns, true)) {
            $this->pdo->exec('ALTER TABLE exchanges ADD COLUMN reply_count INTEGER NOT NULL DEFAULT 0');
        }

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS rate_limit_windows (
                origin_hash    TEXT PRIMARY KEY,
                window_start   TEXT NOT NULL,
                request_count  INTEGER NOT NULL
            )
        SQL);

        // One row per session, at most — a visitor who submits the dialog
        // twice (e.g. a double-tap) overwrites rather than errors, via
        // saveEvaluation()'s INSERT OR REPLACE below. ON DELETE CASCADE so
        // pruneOrphanedSessions()/resetAll() deleting a session (possible
        // even at turn_count=0 — "End session" is always available and
        // shows this dialog) never trips the FK constraint.
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS session_evaluations (
                session_id  TEXT PRIMARY KEY REFERENCES sessions(id) ON DELETE CASCADE,
                answers     TEXT NOT NULL,
                feedback    TEXT,
                created_at  TEXT NOT NULL
            )
        SQL);

        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_sessions_display ON sessions(origin, displayable, last_active_at)');

        // Curriculum search (poor woman's RAG ingestion, bin/import_curriculum.php):
        // requires FTS5, compiled into the sqlite3 lib PHP links against — verified
        // present in this environment; if a deploy target lacks it, this throws at
        // Store construction rather than silently degrading search.
        $this->pdo->exec(<<<SQL
            CREATE VIRTUAL TABLE IF NOT EXISTS curriculum_chunks USING fts5(
                title,
                body,
                path UNINDEXED,
                updated_at UNINDEXED
            )
        SQL);
    }

    /*
    |--------------------------------------------------------------------------
    | Sessions (E-01)
    |--------------------------------------------------------------------------
    */

    /** Creates a session with origin fixed at creation (SQR-05) and no other value assigned yet. */
    public function createSession(string $origin): array
    {
        if (!in_array($origin, ['pilot', 'live', 'study'], true)) {
            throw new InvalidArgumentException("origin must be 'pilot', 'live' or 'study'");
        }

        $id = self::newSessionId(); // opaque, enumeration-resistant (E-01.1); not secret (AP-03)
        $now = self::now();

        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (id, created_at, origin, displayable, turn_count, last_active_at)
             VALUES (:id, :created_at, :origin, 0, 0, :last_active_at)'
        );
        $stmt->execute(['id' => $id, 'created_at' => $now, 'origin' => $origin, 'last_active_at' => $now]);

        return $this->getSession($id);
    }

    private static function newSessionId(): string
    {
        // Crockford Base32: excludes I/L/O/U to avoid visual confusion (human-readable ID)
        static $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $bytes = random_bytes(5); // 40 bits = 8 base32 chars exactly, no padding
        $bits = 0;
        $bitCount = 0;
        $out = '';
        foreach (str_split($bytes) as $byte) {
            $bits = ($bits << 8) | ord($byte);
            $bitCount += 8;
            while ($bitCount >= 5) {
                $bitCount -= 5;
                $out .= $alphabet[($bits >> $bitCount) & 31];
            }
        }
        return $out;
    }

    public function getSession(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sessions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $this->hydrateSession($row);
    }

    /** TI-01's decision call: records the visitor's retention choice. Independent of displayable (SQR-06). */
    public function setConsent(string $id, bool $granted): void
    {
        $stmt = $this->pdo->prepare('UPDATE sessions SET consent_granted = :v WHERE id = :id');
        $stmt->execute(['v' => (int) $granted, 'id' => $id]);
    }

    /**
     * TI-01's decision call, three-checkbox form. ToS agreement is a precondition
     * of using the piece at all (gated in the API layer before this is called,
     * never here) and is recorded for audit. Retention and projection are
     * independent opt-ins (SQR-06/SC-05 — one switch must not govern both), and
     * displayable is set directly from the projection choice: nothing is
     * projected without an explicit yes, regardless of what TF-02 would have
     * allowed.
     */
    public function recordConsentDecision(string $id, bool $tosAgreed, bool $retentionGranted, bool $projectionGranted): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE sessions
             SET tos_agreed = :tos, consent_granted = :retention, projection_consent = :projection, displayable = :displayable
             WHERE id = :id'
        );
        $stmt->execute([
            'tos' => (int) $tosAgreed,
            'retention' => (int) $retentionGranted,
            'projection' => (int) $projectionGranted,
            'displayable' => (int) $projectionGranted,
            'id' => $id,
        ]);
    }

    /**
     * SA-01-8: lift the session's turn ceiling by $by more exchanges. Additive —
     * repeated calls stack (see Sparring::effectiveAllowance). Touches
     * last_active_at so extending also keeps an otherwise-idle session from
     * ageing out mid-decision.
     */
    public function extendSession(string $id, int $by): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE sessions SET bonus_turns = bonus_turns + :by, last_active_at = :now WHERE id = :id'
        );
        $stmt->execute(['by' => $by, 'now' => self::now(), 'id' => $id]);
    }

    /** TF-01 FS-01-6: withholds a session from projection. Never set back to true (AP-05-adjacent, one-way). */
    public function setDisplayable(string $id, bool $displayable): void
    {
        $stmt = $this->pdo->prepare('UPDATE sessions SET displayable = :v WHERE id = :id');
        $stmt->execute(['v' => (int) $displayable, 'id' => $id]);
    }

    /** TF-05: written once from the first contribution (or a generation fallback), never recomputed. */
    public function setScenario(string $id, string $scenario, string $source): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE sessions SET scenario = :s, scenario_source = :src WHERE id = :id'
        );
        $stmt->execute(['s' => $scenario, 'src' => $source, 'id' => $id]);
    }

    /**
     * Session header title, generated once from the first contribution (LLM
     * or trim-fallback — see Sparring::generateAndStoreTitle) and never
     * overwritten after. The `title IS NULL` guard makes a duplicate call a
     * harmless no-op rather than a correctness problem: the client-side
     * fetch that triggers this has no de-dup of its own beyond a best-effort
     * flag (dojo.js), so the store is the actual source of truth for "once."
     */
    public function setTitle(string $id, string $title): void
    {
        $stmt = $this->pdo->prepare('UPDATE sessions SET title = :t WHERE id = :id AND title IS NULL');
        $stmt->execute(['t' => $title, 'id' => $id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Session evaluation
    |--------------------------------------------------------------------------
    */

    /**
     * Records the end-of-session feedback dialog's answers. Always-skippable
     * by design (see dojo.js), so an empty submission is possible — that's
     * treated as nothing to store rather than an empty row, and an unknown
     * session is likewise a no-op: both return false so the caller (the
     * evaluate endpoint) can distinguish "nothing written" from "written."
     * $answers is a flat [questionKey => 1..5] map, already validated by the
     * caller against config.php's EVAL_QUESTIONS/EVAL_SCALE_SIZE.
     */
    public function saveEvaluation(string $sessionId, array $answers, ?string $feedback): bool
    {
        if ($answers === [] && ($feedback === null || $feedback === '')) {
            return false;
        }
        if ($this->getSession($sessionId) === null) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'INSERT OR REPLACE INTO session_evaluations (session_id, answers, feedback, created_at)
             VALUES (:id, :answers, :feedback, :created_at)'
        );
        $stmt->execute([
            'id' => $sessionId,
            'answers' => json_encode($answers, JSON_UNESCAPED_SLASHES),
            'feedback' => $feedback === '' ? null : $feedback,
            'created_at' => self::now(),
        ]);
        return true;
    }

    /** Read-back for tests and the eventual export follow-up (TODO.md) — the app itself is write-only here. */
    public function getEvaluation(string $sessionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM session_evaluations WHERE session_id = :id');
        $stmt->execute(['id' => $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return [
            'sessionId' => $row['session_id'],
            'answers' => json_decode($row['answers'], true),
            'feedback' => $row['feedback'],
            'createdAt' => $row['created_at'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Exchanges (E-02)
    |--------------------------------------------------------------------------
    */

    /**
     * Writes one completed exchange and advances the session's turn count in the same
     * operation, so a failed generation (caller never reaches here) leaves no partial
     * exchange behind (QR-07).
     *
     * $replyToExchangeId: set only when this exchange is a session's first turn
     * (position 1) sent in reply to another exchange's QR code. Incrementing the
     * quoted row's reply_count inside this same transaction, gated on position === 1,
     * is what makes the increment exactly-once — only one position-1 row can ever
     * exist per session, so there is no separate read-then-write race window.
     */
    public function appendExchange(string $sessionId, string $contribution, string $response, ?int $replyToExchangeId = null): array
    {
        $session = $this->getSession($sessionId);
        if ($session === null) {
            throw new RuntimeException("unknown session: $sessionId");
        }

        $position = $session['turnCount'] + 1;
        $now = self::now();

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO exchanges (session_id, visitor_contribution, sparring_response, position, created_at)
                 VALUES (:sid, :c, :r, :pos, :now)'
            );
            $stmt->execute([
                'sid' => $sessionId,
                'c' => $contribution,
                'r' => $response,
                'pos' => $position,
                'now' => $now,
            ]);
            $exchangeId = (int) $this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare(
                'UPDATE sessions SET turn_count = :pos, last_active_at = :now WHERE id = :id'
            );
            $stmt->execute(['pos' => $position, 'now' => $now, 'id' => $sessionId]);

            if ($replyToExchangeId !== null && $position === 1) {
                $stmt = $this->pdo->prepare('UPDATE exchanges SET reply_count = reply_count + 1 WHERE id = :rid');
                $stmt->execute(['rid' => $replyToExchangeId]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'id' => $exchangeId,
            'sessionId' => $sessionId,
            'visitorContribution' => $contribution,
            'sparringResponse' => $response,
            'position' => $position,
            'createdAt' => $now,
            'replyCount' => 0, // a freshly-appended exchange never has replies of its own yet
        ];
    }

    /** Ordered exchanges for a session (E-02, ordered by position). */
    public function getExchanges(string $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM exchanges WHERE session_id = :sid ORDER BY position ASC'
        );
        $stmt->execute(['sid' => $sessionId]);
        return array_map($this->hydrateExchange(...), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Most recent exchange for a session (TF-04 FS-04-4: which pair to show on the wall). */
    public function getLatestExchange(string $sessionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM exchanges WHERE session_id = :sid ORDER BY position DESC LIMIT 1'
        );
        $stmt->execute(['sid' => $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $this->hydrateExchange($row);
    }

    /**
     * A quotable exchange for the QR-reply flow: only resolves if its session is
     * displayable (privacy — an exchange whose visitor never consented to
     * projection must never be readable by scanning/guessing its id, same
     * consent gate the wall itself already respects). Unknown id or a
     * non-displayable session both resolve to null. Returns a narrow shape —
     * deliberately not the full hydrated exchange — so nothing else
     * (visitorContribution, sessionId) can leak through this call site.
     */
    public function getQuotableExchange(int $exchangeId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.sparring_response FROM exchanges e
             JOIN sessions s ON s.id = e.session_id
             WHERE e.id = :id AND s.displayable = 1'
        );
        $stmt->execute(['id' => $exchangeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : ['exchangeId' => $exchangeId, 'text' => $row['sparring_response']];
    }

    /** Every session, any origin (bin/export.php — C-04: extraction is a query against the store). */
    public function getAllSessions(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM sessions ORDER BY created_at ASC');
        return array_map($this->hydrateSession(...), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Every exchange, any session (bin/export.php). */
    public function getAllExchanges(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM exchanges ORDER BY session_id ASC, position ASC');
        return array_map($this->hydrateExchange(...), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * TF-04 FS-04-1/FS-04-3: displayable sessions of the given origins with at least
     * one exchange, most recent first across all of them. $limit total.
     *
     * @param string[] $origins
     */
    public function getDisplayableSessions(array $origins, int $limit): array
    {
        // Placeholders only (one per origin): the values are still bound, never interpolated.
        $placeholders = implode(',', array_fill(0, count($origins), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT s.* FROM sessions s
             WHERE s.origin IN ($placeholders) AND s.displayable = 1
               AND EXISTS (SELECT 1 FROM exchanges e WHERE e.session_id = s.id)
             ORDER BY s.last_active_at DESC
             LIMIT ?"
        );
        // bindValue, not execute([...]), so $limit stays an INTEGER for LIMIT (execute binds strings).
        foreach (array_values($origins) as $i => $origin) {
            $stmt->bindValue($i + 1, $origin, PDO::PARAM_STR);
        }
        $stmt->bindValue(count($origins) + 1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map($this->hydrateSession(...), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /*
    |--------------------------------------------------------------------------
    | Rate limiting (E-03)
    |--------------------------------------------------------------------------
    */

    /**
     * Reads/increments the counting window for a hashed origin, resetting it in place
     * once expired (TF-03: no swept background job on an unattended host).
     * Returns ['allowed' => bool, 'remaining' => int] — remaining is surfaced to
     * debug mode (?debug=1), cheap enough to always compute.
     *
     * $now: window-expiry reference point, defaults to time(). Injectable so a
     * test can check the window-boundary math deterministically instead of
     * sleeping for real (tests/smoke_sparring.php).
     */
    public function checkAndIncrementRateLimit(string $originHash, int $windowSeconds, int $maxRequests, ?int $now = null): array
    {
        $now = $now ?? time();
        $stmt = $this->pdo->prepare('SELECT * FROM rate_limit_windows WHERE origin_hash = :h');
        $stmt->execute(['h' => $originHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false || ($now - strtotime($row['window_start'])) >= $windowSeconds) {
            // fresh window, first request in it
            $stmt = $this->pdo->prepare(
                'INSERT INTO rate_limit_windows (origin_hash, window_start, request_count)
                 VALUES (:h, :ws, 1)
                 ON CONFLICT(origin_hash) DO UPDATE SET window_start = :ws, request_count = 1'
            );
            $stmt->execute(['h' => $originHash, 'ws' => self::now()]);
            return ['allowed' => true, 'remaining' => $maxRequests - 1];
        }

        if ((int) $row['request_count'] >= $maxRequests) {
            return ['allowed' => false, 'remaining' => 0];
        }

        $stmt = $this->pdo->prepare(
            'UPDATE rate_limit_windows SET request_count = request_count + 1 WHERE origin_hash = :h'
        );
        $stmt->execute(['h' => $originHash]);
        return ['allowed' => true, 'remaining' => $maxRequests - ((int) $row['request_count'] + 1)];
    }

    /*
    |--------------------------------------------------------------------------
    | Curriculum search (poor woman's RAG ingestion, bin/import_curriculum.php)
    |--------------------------------------------------------------------------
    |
    | Disk-derived cache, not visitor data: deliberately kept out of getCounts()/
    | resetAll() (scoped to sessions/exchanges/rate-limit windows) so an
    | exhibition-session reset never wipes the curriculum corpus.
    |
    */

    /**
     * Replaces the entire curriculum_chunks table with $chunks in one transaction —
     * the whole-corpus rebuild bin/import_curriculum.php does on every run. Full
     * rebuild rather than incremental upsert: makes a file removed from disk simply
     * absent from $chunks, so its chunk is gone after commit with no separate
     * orphan-sweep needed. $chunks: list of ['title' => string, 'body' => string,
     * 'path' => string]. Returns ['before' => int, 'after' => int] for the
     * importer's summary line.
     */
    public function replaceCurriculumChunks(array $chunks): array
    {
        $before = $this->getCurriculumChunkCount();
        $now = self::now();

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM curriculum_chunks');
            $stmt = $this->pdo->prepare(
                'INSERT INTO curriculum_chunks (title, body, path, updated_at) VALUES (:title, :body, :path, :now)'
            );
            foreach ($chunks as $chunk) {
                $stmt->execute([
                    'title' => $chunk['title'],
                    'body' => $chunk['body'],
                    'path' => $chunk['path'],
                    'now' => $now,
                ]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['before' => $before, 'after' => count($chunks)];
    }

    /** Row count, used by the importer's summary line and by tests. */
    public function getCurriculumChunkCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM curriculum_chunks')->fetchColumn();
    }

    /**
     * Keyword search over curriculum_chunks, ranked by BM25 (more negative = more
     * relevant — ascending sort is correct, don't flip it). Returns [] for an
     * empty or entirely-punctuation query rather than throwing.
     *
     * bm25(curriculum_chunks, 3.0, 1.0) weights a title match 3x a body
     * match — title is short and topic-precise (a page's own name), so a
     * hit there is a much stronger relevance signal than the same token
     * once in a long body. Args are positional over the table's *indexed*
     * columns only (title, body — path/updated_at are UNINDEXED and excluded
     * from bm25 entirely), verified against a real sqlite3 build before
     * relying on it.
     */
    public function searchCurriculum(string $query, int $limit = 5): array
    {
        $sanitized = self::sanitizeFtsQuery($query);
        if ($sanitized === null) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT path, title,
                    snippet(curriculum_chunks, 1, '', '', '…', 12) AS snippet,
                    bm25(curriculum_chunks, 3.0, 1.0) AS score
             FROM curriculum_chunks
             WHERE curriculum_chunks MATCH :q
             ORDER BY score
             LIMIT :limit"
        );
        $stmt->bindValue('q', $sanitized, PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn(array $row): array => [
                'path' => $row['path'],
                'title' => $row['title'],
                'snippet' => $row['snippet'],
                'score' => (float) $row['score'],
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    // Closed-class German function words (articles, pronouns, prepositions,
    // conjunctions, common auxiliary/modal verb forms) dropped from a query
    // before it's OR-joined below. Root cause this exists for: an OR query
    // sums BM25 contributions across every matched term, so a free-text
    // sentence otherwise lets a document that happens to contain many
    // filler words (die, eine, ist, an, zu...) outscore one that contains
    // only the 1-2 rare, actually-relevant terms — confirmed empirically
    // against the real corpus (see tests/smoke_curriculum_retrieval.php).
    // ponytail: hand-curated list, not a real German lemmatizer/stopword
    // library — misses inflected stopword forms (e.g. "einem", "dessen")
    // it wasn't seeded with. Upgrade path: a proper stopword package if
    // this ever proves insufficient.
    private const FTS_STOPWORDS = [
        'der', 'die', 'das', 'dem', 'den', 'des',
        'ein', 'eine', 'einer', 'eines', 'einem', 'einen',
        'und', 'oder', 'aber', 'doch', 'sondern', 'denn',
        'ist', 'sind', 'war', 'waren', 'wird', 'werden', 'wurde', 'wurden', 'sein', 'seins',
        'bin', 'bist', 'seid', 'habe', 'hast', 'hat', 'haben', 'hatte', 'hatten',
        'nicht', 'kein', 'keine', 'keinen', 'keiner', 'keines', 'keinem',
        'zu', 'zur', 'zum', 'an', 'auf', 'in', 'im', 'aus', 'mit', 'nach',
        'bei', 'für', 'von', 'vor', 'über', 'unter', 'durch', 'gegen', 'ohne', 'um',
        'dass', 'wenn', 'weil', 'als', 'wie',
        'ich', 'du', 'er', 'sie', 'es', 'wir', 'ihr', 'man',
        'mein', 'dein', 'unser', 'euer',
        'auch', 'nur', 'schon', 'noch', 'mehr', 'sehr', 'immer', 'so',
        'dann', 'hier', 'da', 'dort', 'etwas', 'alle', 'alles',
        'jede', 'jeder', 'jedes', 'diese', 'dieser', 'dieses', 'jene', 'jener', 'jenes',
    ];

    // Tokens shorter than this stay exact-match — prefix matching on a short
    // token (e.g. "an*") explodes into false positives (matches "Architektur",
    // "Anordnung", "Analyse"...). Longer tokens get a trailing '*' so German
    // inflection (Ziele -> Ziel*) and compounding still recall the base
    // concept without a full stemmer.
    private const FTS_PREFIX_MIN_LENGTH = 5;

    /**
     * FTS5's query syntax (quoting, column filters, NEAR, leading '-') throws a
     * PDOException on malformed input — a real risk here since the eventual
     * caller is free-text a visitor typed, not an operator-authored query.
     * Reduces to a flat OR-of-tokens MATCH expression instead: favors recall
     * over precision on a small corpus, where a strict multi-word AND (FTS5's
     * default) could too easily return nothing. Returns null if $raw has no
     * word/number tokens at all (caller then returns [] without querying).
     */
    private static function sanitizeFtsQuery(string $raw): ?string
    {
        if (!preg_match_all('/[\p{L}\p{N}]+/u', $raw, $matches)) {
            return null;
        }
        $tokens = array_filter(
            $matches[0],
            static fn(string $token): bool => !in_array(mb_strtolower($token), self::FTS_STOPWORDS, true)
        );
        // An all-stopword query (or a corpus-language mismatch) would
        // otherwise filter down to nothing and silently return no results —
        // fall back to the unfiltered tokens rather than lose the query.
        if ($tokens === []) {
            $tokens = $matches[0];
        }
        // Quoted as an FTS5 string literal (embedded '"' doubled, the standard
        // escape) rather than joined bare: an unquoted token that happens to be
        // and/or/not/near (case-insensitively) would otherwise be parsed as an
        // FTS5 operator instead of a search term — exactly the failure this
        // sanitizer exists to prevent. Quoting makes every token a literal
        // match regardless of its text, closing that hole by construction.
        // A '*' appended *after* the closing quote is still valid FTS5 prefix
        // syntax (verified: `"foo"*` behaves the same as bare `foo*`) — so
        // long tokens get prefix matching without losing the operator-keyword
        // protection quoting provides.
        return implode(' OR ', array_map(
            static function (string $token): string {
                $quoted = '"' . str_replace('"', '""', $token) . '"';
                return mb_strlen($token) >= self::FTS_PREFIX_MIN_LENGTH ? $quoted . '*' : $quoted;
            },
            $tokens
        ));
    }

    // Excerpt length for searchCurriculumConcepts() below — enough for the
    // model to actually use the concept (definition + surrounding context),
    // capped so 1-2 chunks stay a bounded addition to a turn-1 prompt rather
    // than the full file. Plain char truncation (no word-boundary care),
    // same style as derive_scenario_statement()'s.
    private const CONCEPT_EXCERPT_MAX_CHARS = 700;

    /**
     * Turn-1 auto-grounding lookup (see prompts/sparring.md's
     * <domain_grounding>/<curriculum_excerpts> and Sparring::processTurn):
     * unlike searchCurriculum() above, this restricts matching to tokens
     * that are also a word in some page's own title — a vocabulary
     * auto-derived from the corpus (SELECT DISTINCT title, stopword-
     * filtered), not hand-maintained, so it stays in sync with
     * data/curriculum/ automatically. This exists because free-text
     * OR-of-every-token matching lets generic content words (not just
     * stopwords) dilute a query past usefulness — confirmed empirically
     * against the real corpus (see tests/smoke_curriculum_retrieval.php).
     * Restricting to title-vocabulary words also gives a clean "nothing
     * relevant" signal for free: a sentence sharing no word with any title
     * naturally returns [] before a query is even built, which a raw BM25
     * score threshold can't do reliably (a single exact-word match can
     * score *weaker* in magnitude than a long irrelevant sentence, since
     * BM25 sums contributions across every matched term).
     *
     * Returns list<array{path: string, title: string, excerpt: string, score: float}>,
     * excerpt truncated to CONCEPT_EXCERPT_MAX_CHARS (not the full body,
     * not the tiny snippet() searchCurriculum() uses for display).
     *
     * ponytail: the corpus (and so the title vocabulary) is overwhelmingly
     * German, but several titles contain plain English words (`Use`, `Case`,
     * `System`, `Force`, `Service`...) — an English contribution (G-04
     * supports both languages) can coincidentally match one of these and
     * surface an unrelated page with real confidence, since nothing here
     * is language-aware. Not a crash risk (the caller's prompt text already
     * says "may or may not be relevant"), just noisier grounding for
     * English visitors than German ones. Upgrade path if this proves to
     * matter in practice: detect the contribution's language before
     * calling this, or restrict the vocabulary to titles/words that don't
     * double as common English ones.
     */
    public function searchCurriculumConcepts(string $text, int $limit = 2): array
    {
        $vocabulary = $this->curriculumTitleVocabulary();
        if (!preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches)) {
            return [];
        }
        $keywords = array_values(array_unique(array_filter(
            $matches[0],
            static fn(string $token): bool => isset($vocabulary[mb_strtolower($token)])
        )));
        if ($keywords === []) {
            return [];
        }

        $query = implode(' OR ', array_map(
            static fn(string $token): string => '"' . str_replace('"', '""', $token) . '"',
            $keywords
        ));

        $stmt = $this->pdo->prepare(
            'SELECT path, title, body, bm25(curriculum_chunks, 3.0, 1.0) AS score
             FROM curriculum_chunks
             WHERE curriculum_chunks MATCH :q
             ORDER BY score
             LIMIT :limit'
        );
        $stmt->bindValue('q', $query, PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn(array $row): array => [
                'path' => $row['path'],
                'title' => $row['title'],
                'excerpt' => self::truncateExcerpt($row['body']),
                'score' => (float) $row['score'],
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * Every distinct page title in the corpus, tokenized and stopword-
     * filtered into a lookup set — recomputed on every call rather than
     * cached, since this only ever runs once per session (turn 1) against
     * a corpus of ~100 rows; caching would be complexity with nothing real
     * to buy.
     *
     * @return array<string, true>
     */
    private function curriculumTitleVocabulary(): array
    {
        $titles = $this->pdo->query('SELECT DISTINCT title FROM curriculum_chunks')->fetchAll(PDO::FETCH_COLUMN);
        $vocabulary = [];
        foreach ($titles as $title) {
            if (!preg_match_all('/[\p{L}\p{N}]+/u', $title, $matches)) {
                continue;
            }
            foreach ($matches[0] as $word) {
                $lower = mb_strtolower($word);
                if (!in_array($lower, self::FTS_STOPWORDS, true)) {
                    $vocabulary[$lower] = true;
                }
            }
        }
        return $vocabulary;
    }

    private static function truncateExcerpt(string $body): string
    {
        $trimmed = trim($body);
        return mb_strlen($trimmed) <= self::CONCEPT_EXCERPT_MAX_CHARS
            ? $trimmed
            : mb_substr($trimmed, 0, self::CONCEPT_EXCERPT_MAX_CHARS) . '…';
    }

    /*
    |--------------------------------------------------------------------------
    | Maintenance (bin/reset_db.php, bin/backup_db.php)
    |--------------------------------------------------------------------------
    */

    /** Row counts across every table this store owns — used by --dry-run and post-reset reporting. */
    public function getCounts(): array
    {
        return [
            'sessions' => (int) $this->pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn(),
            'exchanges' => (int) $this->pdo->query('SELECT COUNT(*) FROM exchanges')->fetchColumn(),
            'rateLimitWindows' => (int) $this->pdo->query('SELECT COUNT(*) FROM rate_limit_windows')->fetchColumn(),
        ];
    }

    /** Empties every table (FK-safe order: exchanges before sessions — session_evaluations cascades). Returns the counts deleted. */
    public function resetAll(): array
    {
        $counts = $this->getCounts();
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM exchanges');
            $this->pdo->exec('DELETE FROM sessions');
            $this->pdo->exec('DELETE FROM rate_limit_windows');
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return $counts;
    }

    /**
     * Consistent-snapshot backup via SQLite's own VACUUM INTO — correct even
     * under WAL journal mode (Store.php ctor), no new dependency needed.
     */
    public function backupTo(string $destPath): void
    {
        $this->pdo->exec('VACUUM INTO ' . $this->pdo->quote($destPath));
    }

    /**
     * Orphaned = zero exchanges (visitor loaded the page, never sent a turn)
     * and stale past $olderThanSeconds — never touches an in-progress visitor.
     * turn_count = 0 implies no exchanges row references the session, so no
     * FK cleanup is needed before the delete (bin/prune_orphaned_sessions.php).
     */
    public function countOrphanedSessions(int $olderThanSeconds): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM sessions WHERE turn_count = 0 AND last_active_at < :cutoff'
        );
        $stmt->execute(['cutoff' => self::cutoff($olderThanSeconds)]);
        return (int) $stmt->fetchColumn();
    }

    /** Deletes the sessions countOrphanedSessions() would count. Returns the number deleted. */
    public function pruneOrphanedSessions(int $olderThanSeconds): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM sessions WHERE turn_count = 0 AND last_active_at < :cutoff'
        );
        $stmt->execute(['cutoff' => self::cutoff($olderThanSeconds)]);
        return $stmt->rowCount();
    }

    /**
     * Deletes one session and everything under it: its exchanges (plain FK,
     * no ON DELETE CASCADE, so removed explicitly) and its evaluation row
     * (session_evaluations cascades on its own). Returns null if no session
     * has that id, else the counts removed. bin/delete_session.php.
     */
    public function deleteSession(string $id): ?array
    {
        if ($this->getSession($id) === null) {
            return null;
        }

        $this->pdo->beginTransaction();
        try {
            $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM exchanges WHERE session_id = :id');
            $countStmt->execute(['id' => $id]);
            $exchangeCount = (int) $countStmt->fetchColumn();

            $delExchanges = $this->pdo->prepare('DELETE FROM exchanges WHERE session_id = :id');
            $delExchanges->execute(['id' => $id]);
            $delSession = $this->pdo->prepare('DELETE FROM sessions WHERE id = :id');
            $delSession->execute(['id' => $id]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['exchanges' => $exchangeCount];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private static function now(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    private static function cutoff(int $olderThanSeconds): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', time() - $olderThanSeconds);
    }

    private function hydrateExchange(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'sessionId' => $row['session_id'],
            'visitorContribution' => $row['visitor_contribution'],
            'sparringResponse' => $row['sparring_response'],
            'position' => (int) $row['position'],
            'createdAt' => $row['created_at'],
            'replyCount' => (int) $row['reply_count'],
        ];
    }

    private function hydrateSession(array $row): array
    {
        return [
            'id' => $row['id'],
            'createdAt' => $row['created_at'],
            'scenarioSource' => $row['scenario_source'],
            'origin' => $row['origin'],
            'scenario' => $row['scenario'],
            'title' => $row['title'],
            'displayable' => (bool) $row['displayable'],
            'consentGranted' => $row['consent_granted'] === null ? null : (bool) $row['consent_granted'],
            'tosAgreed' => $row['tos_agreed'] === null ? null : (bool) $row['tos_agreed'],
            'projectionConsent' => $row['projection_consent'] === null ? null : (bool) $row['projection_consent'],
            'turnCount' => (int) $row['turn_count'],
            'bonusTurns' => (int) $row['bonus_turns'],
            'lastActiveAt' => $row['last_active_at'],
        ];
    }
}
