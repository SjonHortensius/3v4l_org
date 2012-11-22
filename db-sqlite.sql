CREATE TABLE output (hash TEXT UNIQUE, raw BLOB);
CREATE TABLE result (input TEXT, output TEXT, version TEXT, exitCode INTEGER, created TEXT, userTime REAL, systemTime REAL, FOREIGN KEY(input) REFERENCES input(hash), FOREIGN KEY(output) REFERENCES output(hash));
CREATE TABLE input(hash TEXT PRIMARY KEY, source TEXT, type TEXT, FOREIGN KEY (source) REFERENCES input(hash));
CREATE TABLE submit (input TEXT, ip INTEGER, created TEXT CURRENT_TIMESTAMP, updated TEXT, count INTEGER DEFAULT 0, FOREIGN KEY (input) REFERENCES input(hash), UNIQUE (input, ip));