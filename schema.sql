
CREATE TABLE teams (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    logo VARCHAR(255) DEFAULT NULL,
    deleted_at TIMESTAMP DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index for better search performance
CREATE INDEX idx_teams_name ON teams(name);
CREATE INDEX idx_teams_deleted_at ON teams(deleted_at);

COMMENT ON TABLE teams IS 'Anagrafica squadre di calcio';
COMMENT ON COLUMN teams.logo IS 'Path o URL del logo della squadra (es: /uploads/logos/juventus.png)';
COMMENT ON COLUMN teams.deleted_at IS 'Soft delete: se valorizzato, la squadra è considerata eliminata';


-- ============================================
-- Table: tournaments
-- ============================================
CREATE TABLE tournaments (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    date DATE NOT NULL,
    location VARCHAR(200) NOT NULL,
    team_ids INTEGER[] DEFAULT '{}',
    status VARCHAR(20) DEFAULT 'setup' CHECK (status IN ('setup', 'in_progress', 'completed')),
    winner_id INTEGER DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tournaments_winner FOREIGN KEY (winner_id) REFERENCES teams(id) ON DELETE SET NULL
);

-- Index for better search performance
CREATE INDEX idx_tournaments_status ON tournaments(status);
CREATE INDEX idx_tournaments_date ON tournaments(date);
CREATE INDEX idx_tournaments_winner_id ON tournaments(winner_id);

COMMENT ON TABLE tournaments IS 'Tornei ad eliminazione diretta';
COMMENT ON COLUMN tournaments.team_ids IS 'Array di ID delle squadre partecipanti';
COMMENT ON COLUMN tournaments.status IS 'setup: in configurazione, in_progress: in corso, completed: concluso';


-- ============================================
-- Table: tournament_teams (Pivot Table - Optional)
-- ============================================
-- Nota: il backend usa il campo team_ids in tournaments
-- Questa tabella può essere usata per query più efficienti
CREATE TABLE tournament_teams (
    id SERIAL PRIMARY KEY,
    tournament_id INTEGER NOT NULL,
    team_id INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tournament_teams_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    CONSTRAINT fk_tournament_teams_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT unique_tournament_team UNIQUE (tournament_id, team_id)
);

-- Index for better join performance
CREATE INDEX idx_tournament_teams_tournament ON tournament_teams(tournament_id);
CREATE INDEX idx_tournament_teams_team ON tournament_teams(team_id);

COMMENT ON TABLE tournament_teams IS 'Tabella pivot per relazione molti-a-molti tra tornei e squadre (opzionale, il backend usa team_ids)';


-- ============================================
-- Table: matches
-- ============================================
CREATE TABLE matches (
    id SERIAL PRIMARY KEY,
    tournament_id INTEGER NOT NULL,
    round INTEGER NOT NULL,
    match_number INTEGER NOT NULL,
    team1_id INTEGER DEFAULT NULL,
    team2_id INTEGER DEFAULT NULL,
    team1_score INTEGER DEFAULT NULL,
    team2_score INTEGER DEFAULT NULL,
    winner_id INTEGER DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'in_progress', 'completed')),
    played_at TIMESTAMP DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_matches_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    CONSTRAINT fk_matches_team1 FOREIGN KEY (team1_id) REFERENCES teams(id) ON DELETE SET NULL,
    CONSTRAINT fk_matches_team2 FOREIGN KEY (team2_id) REFERENCES teams(id) ON DELETE SET NULL,
    CONSTRAINT fk_matches_winner FOREIGN KEY (winner_id) REFERENCES teams(id) ON DELETE SET NULL,
    CONSTRAINT check_scores CHECK (
        (team1_score IS NULL AND team2_score IS NULL) OR 
        (team1_score IS NOT NULL AND team2_score IS NOT NULL AND team1_score >= 0 AND team2_score >= 0)
    )
);

-- Indexes for better query performance
CREATE INDEX idx_matches_tournament ON matches(tournament_id);
CREATE INDEX idx_matches_round ON matches(tournament_id, round);
CREATE INDEX idx_matches_status ON matches(status);
CREATE INDEX idx_matches_team1 ON matches(team1_id);
CREATE INDEX idx_matches_team2 ON matches(team2_id);

COMMENT ON TABLE matches IS 'Partite del torneo con risultati';
COMMENT ON COLUMN matches.round IS 'Numero del turno (1=primo turno, 2=secondo turno, etc.)';
COMMENT ON COLUMN matches.match_number IS 'Numero progressivo della partita nel turno';
COMMENT ON COLUMN matches.status IS 'pending: da giocare, in_progress: in corso, completed: conclusa';


-- ============================================
-- Functions and Triggers
-- ============================================

-- Funzione generica per updated_at
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger per teams
CREATE TRIGGER update_teams_updated_at
    BEFORE UPDATE ON teams
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- Trigger per tournaments
CREATE TRIGGER update_tournaments_updated_at
    BEFORE UPDATE ON tournaments
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- Trigger per matches
CREATE TRIGGER update_matches_updated_at
    BEFORE UPDATE ON matches
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- ============================================
-- Views (Optional - for easier queries)
-- ============================================

-- View: Complete tournament information with winner
CREATE OR REPLACE VIEW v_tournaments_complete AS
SELECT 
    t.*,
    tw.name as winner_name,
    tw.logo as winner_logo,
    array_length(t.team_ids, 1) as teams_count,
    COUNT(DISTINCT m.id) as total_matches,
    COUNT(DISTINCT CASE WHEN m.status = 'completed' THEN m.id END) as completed_matches
FROM tournaments t
LEFT JOIN teams tw ON t.winner_id = tw.id
LEFT JOIN matches m ON t.id = m.tournament_id
GROUP BY t.id, tw.name, tw.logo;

-- View: Matches with team names
CREATE OR REPLACE VIEW v_matches_complete AS
SELECT 
    m.*,
    t1.name as team1_name,
    t1.logo as team1_logo,
    t2.name as team2_name,
    t2.logo as team2_logo,
    wt.name as winner_name,
    wt.logo as winner_logo,
    t.name as tournament_name
FROM matches m
LEFT JOIN teams t1 ON m.team1_id = t1.id
LEFT JOIN teams t2 ON m.team2_id = t2.id
LEFT JOIN teams wt ON m.winner_id = wt.id
LEFT JOIN tournaments t ON m.tournament_id = t.id;

-- View: Team statistics
CREATE OR REPLACE VIEW v_team_statistics AS
SELECT 
    t.id,
    t.name,
    t.logo,
    COUNT(DISTINCT CASE WHEN t.id = ANY(tour.team_ids) THEN tour.id END) as tournaments_played,
    COUNT(DISTINCT CASE WHEN tour.winner_id = t.id THEN tour.id END) as tournaments_won,
    COUNT(DISTINCT CASE WHEN m.team1_id = t.id OR m.team2_id = t.id THEN m.id END) as matches_played,
    COUNT(DISTINCT CASE WHEN m.winner_id = t.id THEN m.id END) as matches_won,
    COUNT(DISTINCT CASE WHEN m.status = 'completed' AND m.winner_id != t.id AND (m.team1_id = t.id OR m.team2_id = t.id) THEN m.id END) as matches_lost
FROM teams t
LEFT JOIN tournaments tour ON t.id = ANY(tour.team_ids)
LEFT JOIN matches m ON (m.team1_id = t.id OR m.team2_id = t.id)
WHERE t.deleted_at IS NULL
GROUP BY t.id, t.name, t.logo;


-- ============================================
-- Sample Data (Optional - for testing)
-- ============================================

-- Inserimento squadre di esempio
INSERT INTO teams (name, logo) VALUES
    ('Juventus', '/logos/juventus.png'),
    ('Inter', '/logos/inter.png'),
    ('Atalanta', '/logos/atalanta.png'),
    ('Atletico', '/logos/atletico.png'),
    ('Barcellona', '/logos/barcellona.png'),
    ('Bayern', '/logos/bayern.png'),
    ('Borussia Dortmund', '/logos/borussia.png'),
    ('Chelsea', '/logos/chelsea.png'),
    ('Liverpool', '/logos/liverpool.png'),
    ('Man City', '/logos/mancity.png'),
    ('Newcastle', '/logos/newcastle.png'),
    ('PSG', '/logos/psg.png'),
    ('Real Madrid', '/logos/real.png'),
    ('Sporting', '/logos/sporting.png'),
    ('Tottenham', '/logos/totthenam.png'),
    ('Arsenal', '/logos/arsenal.png');

-- Inserimento torneo di esempio
INSERT INTO tournaments (name, date, location, team_ids, status) VALUES
    ('Coppa Italia 2026', '2026-03-15', 'Stadio Olimpico, Roma', ARRAY[1,2,3,4,5,6,7,8], 'setup');

-- Popola la tabella pivot (opzionale)
INSERT INTO tournament_teams (tournament_id, team_id)
SELECT 1, unnest(ARRAY[1,2,3,4,5,6,7,8]);



COMMIT;
