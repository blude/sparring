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
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS sessions (
                id                  TEXT PRIMARY KEY,
                created_at          TEXT NOT NULL,
                scenario_source     TEXT CHECK (scenario_source IN ('first-contribution', 'generated')),
                origin              TEXT NOT NULL CHECK (origin IN ('pilot', 'live')),
                scenario            TEXT,
                title               TEXT,
                displayable         INTEGER NOT NULL DEFAULT 0,
                consent_granted     INTEGER,
                tos_agreed          INTEGER,
                projection_consent  INTEGER,
                turn_count          INTEGER NOT NULL DEFAULT 0,
                last_active_at      TEXT NOT NULL
            )
        SQL);

        // Guard for a store.db created before tos_agreed/projection_consent/title
        // existed — SQLite has no "ADD COLUMN IF NOT EXISTS" on the versions this targets.
        $existing = array_column($this->pdo->query('PRAGMA table_info(sessions)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach (['tos_agreed' => 'INTEGER', 'projection_consent' => 'INTEGER', 'title' => 'TEXT'] as $column => $type) {
            if (!in_array($column, $existing, true)) {
                $this->pdo->exec("ALTER TABLE sessions ADD COLUMN $column $type");
            }
        }

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

        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_sessions_display ON sessions(origin, displayable, last_active_at)');

        // Curriculum search (poor man's RAG ingestion, bin/import_curriculum.php):
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
        if (!in_array($origin, ['pilot', 'live'], true)) {
            throw new InvalidArgumentException("origin must be 'pilot' or 'live'");
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
     * TF-04 FS-04-1/FS-04-3: displayable sessions with at least one exchange, most
     * recent first, live before pilot. $limit total across both groups.
     */
    public function getDisplayableSessions(string $origin, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.* FROM sessions s
             WHERE s.origin = :origin AND s.displayable = 1
               AND EXISTS (SELECT 1 FROM exchanges e WHERE e.session_id = s.id)
             ORDER BY s.last_active_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue('origin', $origin, PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
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
    | Curriculum search (poor man's RAG ingestion, bin/import_curriculum.php)
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
                    bm25(curriculum_chunks) AS score
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
        // Quoted as an FTS5 string literal (embedded '"' doubled, the standard
        // escape) rather than joined bare: an unquoted token that happens to be
        // and/or/not/near (case-insensitively) would otherwise be parsed as an
        // FTS5 operator instead of a search term — exactly the failure this
        // sanitizer exists to prevent. Quoting makes every token a literal
        // match regardless of its text, closing that hole by construction.
        return implode(' OR ', array_map(
            static fn(string $token): string => '"' . str_replace('"', '""', $token) . '"',
            $matches[0]
        ));
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

    /** Empties every table (FK-safe order: exchanges before sessions). Returns the counts deleted. */
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
            'lastActiveAt' => $row['last_active_at'],
        ];
    }
}
