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
# Example here moves limit from 108 to 109
ALTER TABLE result_archive (ALTER CONSTRAINT "isArchive" CHECK (version >= 32 AND version <= 109)) INHERITS (result);

REPLACE FUNCTION result_insert_trigger()
RETURNS TRIGGER AS $$
BEGIN
    IF ( NEW.version < 32 OR NEW.version > 109 ) THEN
        INSERT INTO result_current VALUES (NEW.*);
    ELSE
        INSERT INTO result_archive VALUES (NEW.*);
    END IF;
    RETURN NULL;
END;
$$
LANGUAGE plpgsql;

BEGIN;
INSERT INTO result_archive SELECT * FROM result_current WHERE version = 108;
DELETE FROM result_current WHERE version = 108;
COMMIT;

# redeploy with new PhpShell_Input::trigger

ALTER TABLE result_current (ALTER CONSTRAINT "isCurrent" CHECK (version < 32 OR version > 109)) INHERITS (result);