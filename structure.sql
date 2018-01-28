/* sudo -u postgres pg_dump phpshell -s */
--
-- PostgreSQL database dump
--

-- Dumped from database version 10.1
-- Dumped by pg_dump version 10.1

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: pg_repack; Type: EXTENSION; Schema: -; Owner:
--

CREATE EXTENSION IF NOT EXISTS pg_repack WITH SCHEMA public;


--
-- Name: EXTENSION pg_repack; Type: COMMENT; Schema: -; Owner:
--

COMMENT ON EXTENSION pg_repack IS 'Reorganize tables in PostgreSQL databases with minimal locks';


--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner:
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner:
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

--
-- Name: input_runarchived_clean(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION input_runarchived_clean() RETURNS trigger
    LANGUAGE plpgsql
    AS $$BEGIN
    IF OLD."runArchived" = true AND NEW."runArchived" = false THEN
        DELETE FROM result WHERE input=NEW.id AND version IN (SELECT id from version WHERE eol < OLD.created);
    END IF;

    RETURN NEW;
END;
$$;


ALTER FUNCTION public.input_runarchived_clean() OWNER TO postgres;

--
-- Name: notify_daemon(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION notify_daemon() RETURNS trigger
    LANGUAGE plpgsql
    AS $$BEGIN
  PERFORM pg_notify('daemon', '');
  RETURN NEW;
END;
$$;


ALTER FUNCTION public.notify_daemon() OWNER TO postgres;

--
-- Name: result_insert_trigger(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION result_insert_trigger() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF (NEW.version NOT IN(5,8,10,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,70,71,72,73,74,75,76,77,78,79,80,81,82,83,84,85,86,87,88,89,90,91,92,93,94,95,96,97,98,99,100,101,102,103,104,105,106,107,108,109,110,111,112,113,114,115,116,117,118,119,120,121,122,123,124,125,126,127,128,129,130,131,132,133,134,135,136,137,138,139,140,141,142,143,144,145,146,147,148,149,150,151,152,153,154,155,156,157,158,159,160,161,162,163,164,168,169,170,172,174,175,177,178,179,181,182,183,186,187,189,190,192,194,197,200,204,205,209,212,214,231,234,238,241,245,248,254,258,290,291,292)) THEN
        INSERT INTO result_current VALUES (NEW.*);
    ELSE
        INSERT INTO result_archive VALUES (NEW.*);
    END IF;
    RETURN NULL;
END;
$$;


ALTER FUNCTION public.result_insert_trigger() OWNER TO postgres;

--
-- Name: Table sizes; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "Table sizes" AS
 SELECT (((n.nspname)::text || '.'::text) || (c.relname)::text) AS relation,
    pg_size_pretty(pg_relation_size((c.oid)::regclass)) AS size
   FROM (pg_class c
     LEFT JOIN pg_namespace n ON ((n.oid = c.relnamespace)))
  WHERE (n.nspname <> ALL (ARRAY['pg_catalog'::name, 'information_schema'::name]))
  ORDER BY (pg_relation_size((c.oid)::regclass)) DESC
 LIMIT 32;


ALTER TABLE "Table sizes" OWNER TO postgres;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: assertion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE assertion (
    input integer NOT NULL,
    "outputHash" character varying(28) NOT NULL,
    "user" integer,
    created timestamp without time zone DEFAULT timezone('UTC'::text, now()) NOT NULL
);


ALTER TABLE assertion OWNER TO postgres;

--
-- Name: hits; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE hits (
    "timestamp" timestamp without time zone NOT NULL,
    site character varying,
    remote_addr inet,
    remote_user character varying,
    http_agent character varying,
    ssl_cipher character varying,
    ssl_protocol character varying,
    req_method character varying,
    req_path character varying,
    req_http character varying,
    status integer,
    bytes_sent integer,
    frontend character varying,
    upstream character varying[],
    upstream_response_time real[]
);


ALTER TABLE hits OWNER TO postgres;

--
-- Name: input; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE input (
    short character varying(28) NOT NULL,
    source integer,
    id integer NOT NULL,
    hash character varying(28) NOT NULL,
    state character varying(12) DEFAULT 'new'::character varying NOT NULL,
    run smallint DEFAULT 0 NOT NULL,
    "operationCount" smallint,
    alias character varying(28),
    "user" integer,
    penalty smallint DEFAULT 0 NOT NULL,
    title character varying(64),
    created timestamp without time zone DEFAULT timezone('UTC'::text, now()),
    "runArchived" boolean DEFAULT false,
    "runQuick" smallint,
    "bughuntIgnore" boolean DEFAULT false
);


ALTER TABLE input OWNER TO postgres;

--
-- Name: input_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE input_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE input_id_seq OWNER TO postgres;

--
-- Name: input_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE input_id_seq OWNED BY input.id;


--
-- Name: input_src; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE input_src (
    input integer NOT NULL,
    raw bytea
);


ALTER TABLE input_src OWNER TO postgres;

--
-- Name: operations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE operations (
    input integer NOT NULL,
    operation character varying(32) NOT NULL,
    operand character varying(64),
    count smallint DEFAULT 1
);


ALTER TABLE operations OWNER TO postgres;

--
-- Name: output; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE output (
    hash character varying(28) NOT NULL,
    raw bytea NOT NULL,
    id integer NOT NULL
);


ALTER TABLE output OWNER TO postgres;

--
-- Name: output_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE output_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE output_id_seq OWNER TO postgres;

--
-- Name: output_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE output_id_seq OWNED BY output.id;


--
-- Name: queue; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE queue (
    input character varying(28) NOT NULL,
    version character varying(24),
    "maxPackets" integer DEFAULT 0 NOT NULL,
    "maxRuntime" integer DEFAULT 2500 NOT NULL,
    "maxOutput" integer DEFAULT 32768 NOT NULL
);


ALTER TABLE queue OWNER TO postgres;

--
-- Name: references_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE references_id_seq
    START WITH 1000
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE references_id_seq OWNER TO postgres;

--
-- Name: references; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "references" (
    id integer DEFAULT nextval('references_id_seq'::regclass) NOT NULL,
    operation character varying(24),
    operand character varying(64),
    link character varying(128) NOT NULL,
    name character varying(96) NOT NULL,
    parent integer
);


ALTER TABLE "references" OWNER TO postgres;

--
-- Name: result; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE result (
    input integer NOT NULL,
    output integer NOT NULL,
    version smallint NOT NULL,
    "exitCode" smallint NOT NULL,
    created timestamp without time zone DEFAULT timezone('UTC'::text, now()) NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    run smallint NOT NULL
);


ALTER TABLE result OWNER TO postgres;

--
-- Name: result_archive; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE result_archive (
    input integer,
    output integer,
    version smallint,
    "exitCode" smallint,
    created timestamp without time zone DEFAULT timezone('UTC'::text, now()),
    "userTime" real,
    "systemTime" real,
    "maxMemory" integer,
    run smallint,
    CONSTRAINT "isArchive" CHECK ((version = ANY (ARRAY[5, 8, 10, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92, 93, 94, 95, 96, 97, 98, 99, 100, 101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121, 122, 123, 124, 125, 126, 127, 128, 129, 130, 131, 132, 133, 134, 135, 136, 137, 138, 139, 140, 141, 142, 143, 144, 145, 146, 147, 148, 149, 150, 151, 152, 153, 154, 155, 156, 157, 158, 159, 160, 161, 162, 163, 164, 168, 169, 170, 172, 174, 175, 177, 178, 179, 181, 182, 183, 186, 187, 189, 190, 192, 194, 197, 200, 204, 205, 209, 212, 214, 231, 234, 238, 241, 245, 248, 254, 258, 290, 291, 292])))
)
INHERITS (result);


ALTER TABLE result_archive OWNER TO postgres;

--
-- Name: result_current; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE result_current (
    input integer,
    output integer,
    version smallint,
    "exitCode" smallint,
    created timestamp without time zone DEFAULT timezone('UTC'::text, now()),
    "userTime" real,
    "systemTime" real,
    "maxMemory" integer,
    run smallint,
    CONSTRAINT "isCurrent" CHECK ((version <> ALL (ARRAY[5, 8, 10, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92, 93, 94, 95, 96, 97, 98, 99, 100, 101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121, 122, 123, 124, 125, 126, 127, 128, 129, 130, 131, 132, 133, 134, 135, 136, 137, 138, 139, 140, 141, 142, 143, 144, 145, 146, 147, 148, 149, 150, 151, 152, 153, 154, 155, 156, 157, 158, 159, 160, 161, 162, 163, 164, 168, 169, 170, 172, 174, 175, 177, 178, 179, 181, 182, 183, 186, 187, 189, 190, 192, 194, 197, 200, 204, 205, 209, 212, 214, 231, 234, 238, 241, 245, 248, 254, 258, 290, 291, 292])))
)
INHERITS (result);


ALTER TABLE result_current OWNER TO postgres;

--
-- Name: version; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE version (
    name character varying(24) NOT NULL,
    released date DEFAULT now(),
    "order" integer,
    command character varying(128) DEFAULT '/bin/php-XXX -c /etc -q'::character varying NOT NULL,
    "isHelper" boolean DEFAULT false NOT NULL,
    id smallint NOT NULL,
    eol date
);


ALTER TABLE version OWNER TO postgres;

--
-- Name: result_bughunt; Type: MATERIALIZED VIEW; Schema: public; Owner: postgres
--

CREATE MATERIALIZED VIEW result_bughunt AS
 SELECT r.input,
    r.output,
    r.version,
    r."exitCode",
    r.created,
    r."userTime",
    r."systemTime",
    r."maxMemory"
   FROM ((result_current r
     JOIN input i ON ((i.id = r.input)))
     JOIN version v ON ((v.id = r.version)))
  WHERE ((NOT i."bughuntIgnore") AND ((v."isHelper" = false) OR ((v.name)::text ~~ 'rfc-%'::text)) AND (v.eol > now()) AND ((now() - (v.released)::timestamp with time zone) < '1 year'::interval) AND (r.run = i.run))
  ORDER BY i.id
  WITH NO DATA;


ALTER TABLE result_bughunt OWNER TO postgres;

--
-- Name: search_haveoperand; Type: MATERIALIZED VIEW; Schema: public; Owner: postgres
--

CREATE MATERIALIZED VIEW search_haveoperand AS
 SELECT DISTINCT operations.operation
   FROM operations
  WHERE (NOT (operations.operand IS NULL))
  WITH NO DATA;


ALTER TABLE search_haveoperand OWNER TO postgres;

--
-- Name: search_operationcount; Type: MATERIALIZED VIEW; Schema: public; Owner: postgres
--

CREATE MATERIALIZED VIEW search_operationcount AS
 SELECT count(*) AS count,
    operations.operation
   FROM operations
  GROUP BY operations.operation
  ORDER BY (count(*)) DESC
  WITH NO DATA;


ALTER TABLE search_operationcount OWNER TO postgres;

--
-- Name: search_popularoperands; Type: MATERIALIZED VIEW; Schema: public; Owner: postgres
--

CREATE MATERIALIZED VIEW search_popularoperands AS
 SELECT operations.operand AS text,
    sum(operations.count) AS size
   FROM operations
  WHERE (((operations.operation)::text = 'INIT_FCALL'::text) AND ((operations.operand)::text <> ALL (ARRAY[('var_dump'::character varying)::text, ('print_r'::character varying)::text])))
  GROUP BY operations.operand
  ORDER BY (sum(operations.count)) DESC
 LIMIT 150
  WITH NO DATA;


ALTER TABLE search_popularoperands OWNER TO postgres;

--
-- Name: submit; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE submit (
    input integer NOT NULL,
    ip inet NOT NULL,
    created timestamp without time zone DEFAULT timezone('UTC'::text, now()),
    updated timestamp without time zone,
    count integer DEFAULT 1
);


ALTER TABLE submit OWNER TO postgres;

--
-- Name: tx_in; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE tx_in (
    transaction character varying(64) NOT NULL,
    "user" integer,
    amount integer
);


ALTER TABLE tx_in OWNER TO postgres;

--
-- Name: COLUMN tx_in.amount; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN tx_in.amount IS 'satoshis received by us';


--
-- Name: tx_out; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE tx_out (
    "user" integer NOT NULL,
    product integer NOT NULL,
    amount real NOT NULL,
    script integer,
    created timestamp without time zone DEFAULT timezone('UTC'::text, now()) NOT NULL,
    product_price integer
);


ALTER TABLE tx_out OWNER TO postgres;

--
-- Name: COLUMN tx_out.amount; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN tx_out.amount IS 'number of products bought';


--
-- Name: COLUMN tx_out.product_price; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN tx_out.product_price IS 'product.price at time of spending';


--
-- Name: tx_product_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE tx_product_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE tx_product_id_seq OWNER TO postgres;

--
-- Name: tx_product; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE tx_product (
    id integer DEFAULT nextval('tx_product_id_seq'::regclass) NOT NULL,
    per_script boolean NOT NULL,
    price integer NOT NULL,
    description character varying(128) NOT NULL,
    key character varying(32)
);


ALTER TABLE tx_product OWNER TO postgres;

--
-- Name: user; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "user" (
    name character varying(15) NOT NULL,
    created timestamp without time zone DEFAULT timezone('UTC'::text, now()),
    last_login timestamp without time zone,
    login_count integer DEFAULT 0 NOT NULL,
    id integer NOT NULL,
    "btcAddress" character varying(35)
);


ALTER TABLE "user" OWNER TO postgres;

--
-- Name: user_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE user_id_seq OWNER TO postgres;

--
-- Name: user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE user_id_seq OWNED BY "user".id;


--
-- Name: version_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE version_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE version_id_seq OWNER TO postgres;

--
-- Name: version_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE version_id_seq OWNED BY version.id;


--
-- Name: input id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY input ALTER COLUMN id SET DEFAULT nextval('input_id_seq'::regclass);


--
-- Name: output id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY output ALTER COLUMN id SET DEFAULT nextval('output_id_seq'::regclass);


--
-- Name: user id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "user" ALTER COLUMN id SET DEFAULT nextval('user_id_seq'::regclass);


--
-- Name: version id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY version ALTER COLUMN id SET DEFAULT nextval('version_id_seq'::regclass);


--
-- Name: assertion assertion_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY assertion
    ADD CONSTRAINT assertion_pkey PRIMARY KEY (input);


--
-- Name: result_current inputVersionRun; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY result_current
    ADD CONSTRAINT "inputVersionRun" UNIQUE (input, version, run);

ALTER TABLE result_current CLUSTER ON "inputVersionRun";


--
-- Name: result_archive inputVersionRunArchive; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY result_archive
    ADD CONSTRAINT "inputVersionRunArchive" UNIQUE (input, version, run);

ALTER TABLE result_archive CLUSTER ON "inputVersionRunArchive";


--
-- Name: input input_hash_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY input
    ADD CONSTRAINT input_hash_key UNIQUE (hash);


--
-- Name: input input_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY input
    ADD CONSTRAINT input_pkey PRIMARY KEY (id);

ALTER TABLE input CLUSTER ON input_pkey;


--
-- Name: input input_short_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY input
    ADD CONSTRAINT input_short_key UNIQUE (short);


--
-- Name: input_src input_src_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY input_src
    ADD CONSTRAINT input_src_pkey PRIMARY KEY (input);

ALTER TABLE input_src CLUSTER ON input_src_pkey;


--
-- Name: operations operations_inputOp; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY operations
    ADD CONSTRAINT "operations_inputOp" UNIQUE (input, operation, operand);

ALTER TABLE operations CLUSTER ON "operations_inputOp";


--
-- Name: output output_hash; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY output
    ADD CONSTRAINT output_hash UNIQUE (hash);


--
-- Name: output output_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY output
    ADD CONSTRAINT output_pkey PRIMARY KEY (id);


--
-- Name: references references_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "references"
    ADD CONSTRAINT references_pkey PRIMARY KEY (id);

ALTER TABLE "references" CLUSTER ON references_pkey;


--
-- Name: submit submit_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY submit
    ADD CONSTRAINT submit_pkey PRIMARY KEY (input, ip);


--
-- Name: tx_in tx_in_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY tx_in
    ADD CONSTRAINT tx_in_pkey PRIMARY KEY (transaction);


--
-- Name: tx_product tx_product_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY tx_product
    ADD CONSTRAINT tx_product_key UNIQUE (key);


--
-- Name: tx_product tx_products_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY tx_product
    ADD CONSTRAINT tx_products_pkey PRIMARY KEY (id);

ALTER TABLE tx_product CLUSTER ON tx_products_pkey;


--
-- Name: user user_name; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "user"
    ADD CONSTRAINT user_name UNIQUE (name);


--
-- Name: user user_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "user"
    ADD CONSTRAINT user_pkey PRIMARY KEY (id);


--
-- Name: version version_name; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY version
    ADD CONSTRAINT version_name UNIQUE (name);


--
-- Name: version version_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY version
    ADD CONSTRAINT version_pkey PRIMARY KEY (id);

ALTER TABLE version CLUSTER ON version_pkey;


--
-- Name: inputAlias; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "inputAlias" ON input USING btree (alias);


--
-- Name: input_bhignore; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX input_bhignore ON input USING btree ("bughuntIgnore") WHERE (NOT "bughuntIgnore");


--
-- Name: operations_search; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX operations_search ON operations USING btree (operand, operation) WHERE (NOT (operand IS NULL));


--
-- Name: operations_search_quick; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX operations_search_quick ON operations USING brin (operation) WITH (pages_per_range='16');


--
-- Name: resultBughunt; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "resultBughunt" ON result_bughunt USING btree (input, version);


--
-- Name: result_exitCode; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_exitCode" ON result_current USING brin ("exitCode");


--
-- Name: input input_runarchived_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER input_runarchived_trigger AFTER UPDATE OF "runArchived" ON input FOR EACH ROW EXECUTE PROCEDURE input_runarchived_clean();


--
-- Name: result insert_result_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER insert_result_trigger BEFORE INSERT ON result FOR EACH ROW EXECUTE PROCEDURE result_insert_trigger();


--
-- Name: queue queue_insert_notify; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER queue_insert_notify AFTER INSERT ON queue FOR EACH STATEMENT EXECUTE PROCEDURE notify_daemon();


--
-- Name: assertion assertion_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY assertion
    ADD CONSTRAINT assertion_input_fkey FOREIGN KEY (input) REFERENCES input(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: assertion assertion_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY assertion
    ADD CONSTRAINT assertion_user_fkey FOREIGN KEY ("user") REFERENCES "user"(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: input input_runQuick_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY input
    ADD CONSTRAINT "input_runQuick_fkey" FOREIGN KEY ("runQuick") REFERENCES version(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: input input_source_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY input
    ADD CONSTRAINT input_source_fkey FOREIGN KEY (source) REFERENCES input(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: input_src input_src_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY input_src
    ADD CONSTRAINT input_src_input_fkey FOREIGN KEY (input) REFERENCES input(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: input input_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY input
    ADD CONSTRAINT input_user_fkey FOREIGN KEY ("user") REFERENCES "user"(id) ON UPDATE RESTRICT ON DELETE SET NULL;


--
-- Name: operations operations_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY operations
    ADD CONSTRAINT operations_input_fkey FOREIGN KEY (input) REFERENCES input(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: queue queue_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY queue
    ADD CONSTRAINT queue_input_fkey FOREIGN KEY (input) REFERENCES input(short) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: queue queue_version_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY queue
    ADD CONSTRAINT queue_version_fkey FOREIGN KEY (version) REFERENCES version(name) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: references references_parent_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "references"
    ADD CONSTRAINT references_parent_fkey FOREIGN KEY (parent) REFERENCES "references"(id) ON UPDATE RESTRICT ON DELETE SET NULL;


--
-- Name: result_archive result_archive_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY result_archive
    ADD CONSTRAINT result_archive_input_fkey FOREIGN KEY (input) REFERENCES input(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: result_archive result_archive_output_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY result_archive
    ADD CONSTRAINT result_archive_output_fkey FOREIGN KEY (output) REFERENCES output(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: result_archive result_archive_version_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY result_archive
    ADD CONSTRAINT result_archive_version_fkey FOREIGN KEY (version) REFERENCES version(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: result_current result_current_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY result_current
    ADD CONSTRAINT result_current_input_fkey FOREIGN KEY (input) REFERENCES input(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: result_current result_current_output_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY result_current
    ADD CONSTRAINT result_current_output_fkey FOREIGN KEY (output) REFERENCES output(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: result_current result_current_version_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY result_current
    ADD CONSTRAINT result_current_version_fkey FOREIGN KEY (version) REFERENCES version(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: submit submit_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY submit
    ADD CONSTRAINT submit_input_fkey FOREIGN KEY (input) REFERENCES input(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: tx_in tx_in_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY tx_in
    ADD CONSTRAINT tx_in_user_fkey FOREIGN KEY ("user") REFERENCES "user"(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: tx_out tx_out_product_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY tx_out
    ADD CONSTRAINT tx_out_product_fkey FOREIGN KEY (product) REFERENCES tx_product(id) ON UPDATE RESTRICT ON DELETE SET NULL;


--
-- Name: tx_out tx_out_script_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY tx_out
    ADD CONSTRAINT tx_out_script_fkey FOREIGN KEY (script) REFERENCES input(id) ON UPDATE RESTRICT ON DELETE SET NULL;


--
-- Name: tx_out tx_out_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY tx_out
    ADD CONSTRAINT tx_out_user_fkey FOREIGN KEY ("user") REFERENCES "user"(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO PUBLIC;


--
-- Name: assertion; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT ON TABLE assertion TO website;


--
-- Name: input; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,UPDATE ON TABLE input TO website;
GRANT SELECT ON TABLE input TO daemon;


--
-- Name: input.state; Type: ACL; Schema: public; Owner: postgres
--

GRANT UPDATE(state) ON TABLE input TO daemon;


--
-- Name: input.run; Type: ACL; Schema: public; Owner: postgres
--

GRANT UPDATE(run) ON TABLE input TO daemon;


--
-- Name: input.operationCount; Type: ACL; Schema: public; Owner: postgres
--

GRANT UPDATE("operationCount") ON TABLE input TO website;


--
-- Name: input.penalty; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT(penalty),UPDATE(penalty) ON TABLE input TO daemon;


--
-- Name: input.runArchived; Type: ACL; Schema: public; Owner: postgres
--

GRANT UPDATE("runArchived") ON TABLE input TO website;


--
-- Name: input.runQuick; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT("runQuick"),INSERT("runQuick"),UPDATE("runQuick") ON TABLE input TO website;


--
-- Name: input_id_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,USAGE ON SEQUENCE input_id_seq TO website;


--
-- Name: input_src; Type: ACL; Schema: public; Owner: postgres
--

REVOKE ALL ON TABLE input_src FROM postgres;
GRANT SELECT,INSERT,REFERENCES,TRIGGER,TRUNCATE ON TABLE input_src TO postgres;
GRANT SELECT,INSERT ON TABLE input_src TO website;
GRANT SELECT ON TABLE input_src TO daemon;


--
-- Name: operations; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE operations TO website;
GRANT SELECT ON TABLE operations TO daemon;


--
-- Name: output; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT ON TABLE output TO daemon;
GRANT SELECT ON TABLE output TO website;


--
-- Name: output_id_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,USAGE ON SEQUENCE output_id_seq TO daemon;


--
-- Name: queue; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,DELETE ON TABLE queue TO daemon;
GRANT SELECT,INSERT ON TABLE queue TO website;


--
-- Name: references_id_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,USAGE ON SEQUENCE references_id_seq TO website;


--
-- Name: references; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON TABLE "references" TO website;


--
-- Name: result; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON TABLE result TO website;
GRANT SELECT,INSERT,DELETE ON TABLE result TO daemon;


--
-- Name: result_archive; Type: ACL; Schema: public; Owner: postgres
--

GRANT INSERT,DELETE ON TABLE result_archive TO daemon;


--
-- Name: result_current; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,DELETE ON TABLE result_current TO daemon;
GRANT SELECT ON TABLE result_current TO website;


--
-- Name: version; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON TABLE version TO website;
GRANT SELECT ON TABLE version TO daemon;


--
-- Name: result_bughunt; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON TABLE result_bughunt TO website;


--
-- Name: search_haveoperand; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON TABLE search_haveoperand TO website;


--
-- Name: search_operationcount; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON TABLE search_operationcount TO website;


--
-- Name: search_popularoperands; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON TABLE search_popularoperands TO website;


--
-- Name: submit; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,UPDATE ON TABLE submit TO website;
GRANT SELECT ON TABLE submit TO daemon;


--
-- Name: tx_in; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON TABLE tx_in TO website;


--
-- Name: tx_out; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT ON TABLE tx_out TO website;


--
-- Name: tx_product; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON TABLE tx_product TO website;


--
-- Name: user; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,UPDATE ON TABLE "user" TO website;


--
-- Name: user_id_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON SEQUENCE user_id_seq TO website;


--
-- PostgreSQL database dump complete
--

