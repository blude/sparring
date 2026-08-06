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
                displayable         INTEGER NOT NULL DEFAULT 0,
                consent_granted     INTEGER,
                tos_agreed          INTEGER,
                projection_consent  INTEGER,
                turn_count          INTEGER NOT NULL DEFAULT 0,
                last_active_at      TEXT NOT NULL
            )
        SQL);

        // Guard for a store.db created before tos_agreed/projection_consent existed —
        // SQLite has no "ADD COLUMN IF NOT EXISTS" on the versions this targets.
        $existing = array_column($this->pdo->query('PRAGMA table_info(sessions)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach (['tos_agreed', 'projection_consent'] as $column) {
            if (!in_array($column, $existing, true)) {
                $this->pdo->exec("ALTER TABLE sessions ADD COLUMN $column INTEGER");
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

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS rate_limit_windows (
                origin_hash    TEXT PRIMARY KEY,
                window_start   TEXT NOT NULL,
                request_count  INTEGER NOT NULL
            )
        SQL);

        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_sessions_display ON sessions(origin, displayable, last_active_at)');
    }

    // --- Sessions (E-01) ---

    /** Creates a session with origin fixed at creation (SQR-05) and no other value assigned yet. */
    public function createSession(string $origin): array
    {
        if (!in_array($origin, ['pilot', 'live'], true)) {
            throw new InvalidArgumentException("origin must be 'pilot' or 'live'");
        }

        $id = bin2hex(random_bytes(16)); // opaque, enumeration-resistant (E-01.1); not secret (AP-03)
        $now = self::now();

        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (id, created_at, origin, displayable, turn_count, last_active_at)
             VALUES (:id, :created_at, :origin, 0, 0, :last_active_at)'
        );
        $stmt->execute(['id' => $id, 'created_at' => $now, 'origin' => $origin, 'last_active_at' => $now]);

        return $this->getSession($id);
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

    // --- Exchanges (E-02) ---

    /**
     * Writes one completed exchange and advances the session's turn count in the same
     * operation, so a failed generation (caller never reaches here) leaves no partial
     * exchange behind (QR-07).
     */
    public function appendExchange(string $sessionId, string $contribution, string $response): array
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

    // --- Rate limiting (E-03) ---

    /**
     * Reads/increments the counting window for a hashed origin, resetting it in place
     * once expired (TF-03: no swept background job on an unattended host).
     * Returns true if the request is within the allowance.
     */
    public function checkAndIncrementRateLimit(string $originHash, int $windowSeconds, int $maxRequests): bool
    {
        $now = time();
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
            return true;
        }

        if ((int) $row['request_count'] >= $maxRequests) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE rate_limit_windows SET request_count = request_count + 1 WHERE origin_hash = :h'
        );
        $stmt->execute(['h' => $originHash]);
        return true;
    }

    // --- Helpers ---

    private static function now(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
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
            'displayable' => (bool) $row['displayable'],
            'consentGranted' => $row['consent_granted'] === null ? null : (bool) $row['consent_granted'],
            'tosAgreed' => $row['tos_agreed'] === null ? null : (bool) $row['tos_agreed'],
            'projectionConsent' => $row['projection_consent'] === null ? null : (bool) $row['projection_consent'],
            'turnCount' => (int) $row['turn_count'],
            'lastActiveAt' => $row['last_active_at'],
        ];
    }
}
