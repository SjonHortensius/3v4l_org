# preparations
ALTER TABLE result ALTER COLUMN "userTime" TYPE real, ALTER COLUMN "systemTime" type real, ALTER COLUMN "maxMemory" type integer, ALTER COLUMN "run" type smallint;
# 6.3G > 5.5G

# downtime
ALTER TABLE input DROP CONSTRAINT input_pkey CASCADE;
CREATE UNIQUE INDEX input_short ON input USING btree(short);
CREATE UNIQUE INDEX output_hash ON output USING btree(hash);

CREATE SEQUENCE input_id_seq;
ALTER TABLE input RENAME "type" TO "id";
ALTER SEQUENCE input_id_seq OWNED BY input.id;
ALTER TABLE input ALTER COLUMN "id" TYPE integer USING nextval('input_id_seq'::regclass), ALTER COLUMN "id" SET DEFAULT nextval('input_id_seq'::regclass), ALTER COLUMN "id" SET NOT NULL;
ALTER TABLE input ADD PRIMARY KEY(id);

select * from input order by id asc limit 3;

UPDATE operations SET input = '0'||(SELECT id FROM input WHERE short = input);
ALTER TABLE operations ALTER COLUMN "input" TYPE integer USING CAST(input AS integer);
ALTER TABLE operations ADD FOREIGN KEY ("input") REFERENCES input("id") ON UPDATE RESTRICT ON DELETE CASCADE;

UPDATE input SET source = (SELECT id FROM input WHERE short = source) WHERE source IS NOT NULL;
ALTER TABLE input ALTER COLUMN "source" TYPE integer USING CAST(source AS integer);
ALTER TABLE input ADD FOREIGN KEY ("source") REFERENCES input("id") ON UPDATE RESTRICT ON DELETE CASCADE;

CREATE SEQUENCE output_id_seq;
ALTER TABLE output ADD COLUMN "id" integer, ALTER COLUMN "id" SET DEFAULT nextval('output_id_seq'::regclass);
UPDATE output SET id = nextval('output_id_seq'::regclass);
ALTER TABLE output ALTER COLUMN "id" SET NOT NULL;
ALTER SEQUENCE output_id_seq OWNED BY output.id;
ALTER TABLE output DROP CONSTRAINT output_pkey CASCADE, ADD PRIMARY KEY(id);

CREATE SEQUENCE version_id_seq;
ALTER TABLE version DROP CONSTRAINT version_pkey CASCADE, ADD COLUMN "id" smallint DEFAULT nextval('version_id_seq'::regclass), ADD PRIMARY KEY(id);
ALTER SEQUENCE version_id_seq OWNED BY version.id;
UPDATE version SET "id" = nextval('version_id_seq'::regclass);
ALTER TABLE version ALTER COLUMN "id" SET NOT NULL;

UPDATE submit SET input = (SELECT id FROM input WHERE short = input);
ALTER TABLE submit ALTER COLUMN "input" TYPE integer USING CAST(input AS integer);
ALTER TABLE submit ADD FOREIGN KEY ("input") REFERENCES input("id") ON UPDATE RESTRICT ON DELETE CASCADE;

UPDATE assertion SET input = (SELECT id FROM input WHERE short = input);
ALTER TABLE assertion ALTER COLUMN "input" TYPE integer USING CAST(input AS integer);
ALTER TABLE assertion ADD FOREIGN KEY ("input") REFERENCES input("id") ON UPDATE RESTRICT ON DELETE CASCADE;

CREATE TABLE result2 (
    input integer NOT NULL,
    output integer NOT NULL,
    version smallint NOT NULL,
    "exitCode" smallint NOT NULL,
    created timestamp without time zone NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    run smallint DEFAULT 0 NOT NULL
);

INSERT INTO result2
	SELECT input.id, output.id, version.id, "exitCode", created, "userTime", "systemTime", "maxMemory", result.run FROM result
	INNER JOIN input on (input.short = result.input)
	INNER JOIN output on (output.hash = result.output)
	INNER JOIN version on (version.name = result.version);

DROP TABLE result;
ALTER TABLE result2 RENAME TO result;

GRANT INSERT ON TABLE result TO daemon;
GRANT SELECT ON TABLE result TO website;
GRANT USAGE, SELECT ON SEQUENCE input_id_seq TO website;
GRANT USAGE, SELECT ON SEQUENCE output_id_seq TO daemon;

ALTER TABLE result
	ADD CONSTRAINT "inputVersionRun" UNIQUE (input, version, run),
	ADD CONSTRAINT result_input_fkey FOREIGN KEY (input) REFERENCES input(id) ON UPDATE RESTRICT ON DELETE CASCADE,
	ADD CONSTRAINT result_output_fkey FOREIGN KEY (output) REFERENCES output(id) ON UPDATE RESTRICT ON DELETE CASCADE,
	ADD CONSTRAINT result_version_fkey FOREIGN KEY (version) REFERENCES version(id) ON UPDATE RESTRICT ON DELETE CASCADE;

# deploy