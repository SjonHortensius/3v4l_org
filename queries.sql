# list of unique outputs per input
SELECT short, array_agg(DISTINCT output) as output
FROM result
JOIN input ON (input.id = input)
JOIN version ON (version.id = version)
WHERE NOT "isHelper" AND output != 11
GROUP BY short
HAVING COUNT(DISTINCT output) < 3
LIMIT 30;

# archive a version. First up archive limit, then update trigger + move rows, then up current limit.
# determine maximum version in _current (from Input::trigger): SELECT * FROM version WHERE released > now() - '2 years'::interval ORDER BY released ASC LIMIT 1;

BEGIN;
  ALTER TABLE result_archive DROP CONSTRAINT "isArchive";
  ALTER TABLE result_archive ADD CONSTRAINT "isArchive" CHECK (version >= 32 AND version < 139);
COMMIT;

CREATE OR REPLACE FUNCTION result_insert_trigger()
RETURNS TRIGGER AS $$
BEGIN
    IF ( NEW.version < 32 OR NEW.version >= 139 ) THEN
        INSERT INTO result_current VALUES (NEW.*);
    ELSE
        INSERT INTO result_archive VALUES (NEW.*);
    END IF;
    RETURN NULL;
END;
$$
LANGUAGE plpgsql;

BEGIN;
  INSERT INTO result_archive SELECT * FROM result_current WHERE (version >= 32 AND version < 139);
  DELETE FROM result_current WHERE (version >= 32 AND version < 139);
COMMIT;

BEGIN;
  ALTER TABLE result_current DROP CONSTRAINT "isCurrent";
  ALTER TABLE result_current ADD CONSTRAINT "isCurrent" CHECK (version < 32 OR version >= 139);
COMMIT;