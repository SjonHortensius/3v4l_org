# list of unique outputs per input
SELECT short, array_agg(DISTINCT output) as output
FROM result
JOIN input ON (input.id = input)
JOIN version ON (version.id = version)
WHERE NOT "isHelper" AND output != 11
GROUP BY short
HAVING COUNT(DISTINCT output) < 3
LIMIT 30;