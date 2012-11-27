CREATE TABLE input(
	hash TEXT PRIMARY KEY,
	source TEXT,
	type TEXT,
	FOREIGN KEY (source) REFERENCES input(hash)
);

CREATE TABLE output(
	hash TEXT PRIMARY KEY,
	raw BLOB
);

CREATE TABLE result(
	input TEXT,
	output TEXT,
	version TEXT,
	exitCode INTEGER,
	created TEXT,
	userTime REAL,
	systemTime REAL,
	maxMemory REAL,
	UNIQUE (input, version),
	FOREIGN KEY(input) REFERENCES input(hash),
	FOREIGN KEY(output) REFERENCES output(hash)
);

CREATE TABLE submit(
	input TEXT,
	ip TEXT,
	created TEXT CURRENT_TIMESTAMP,
	updated TEXT,
	count INTEGER DEFAULT 0,
	UNIQUE (input, ip),
	FOREIGN KEY (input) REFERENCES input(hash)
);

CREATE TABLE version (
	name TEXT PRIMARY KEY,
	released TEXT
);
/*insert into version select version,null from result where input = '2uRXo' ORDER BY 1000*substr(version, 1, 1)+100*substr(version,3,1)+substr(version, 5);*/
