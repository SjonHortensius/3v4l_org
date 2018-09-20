/* sudo -u postgres pg_dump phpshell -s */
--
-- PostgreSQL database dump
--

-- Dumped from database version 10.5
-- Dumped by pg_dump version 10.5

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
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


--
-- Name: pg_stat_statements; Type: EXTENSION; Schema: -; Owner:
--

CREATE EXTENSION IF NOT EXISTS pg_stat_statements WITH SCHEMA public;


--
-- Name: EXTENSION pg_stat_statements; Type: COMMENT; Schema: -; Owner:
--

COMMENT ON EXTENSION pg_stat_statements IS 'track execution statistics of all SQL statements executed';


--
-- Name: input_runarchived_clean(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.input_runarchived_clean() RETURNS trigger
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

CREATE FUNCTION public.notify_daemon() RETURNS trigger
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

CREATE FUNCTION public.result_insert_trigger() RETURNS trigger
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

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: assertion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.assertion (
    input integer NOT NULL,
    "outputHash" character varying(28) NOT NULL,
    "user" integer,
    created timestamp without time zone DEFAULT timezone('UTC'::text, now()) NOT NULL
);


ALTER TABLE public.assertion OWNER TO postgres;

--
-- Name: functionCall; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public."functionCall" (
    input integer NOT NULL,
    function character varying(64) NOT NULL
);


ALTER TABLE public."functionCall" OWNER TO postgres;

--
-- Name: hits; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.hits (
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


ALTER TABLE public.hits OWNER TO postgres;

--
-- Name: input; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.input (
    short character varying(8) NOT NULL,
    source integer,
    id integer NOT NULL,
    hash character varying(28) NOT NULL,
    state character varying(12) DEFAULT 'new'::character varying NOT NULL,
    run smallint DEFAULT 0 NOT NULL,
    "operationCount" smallint,
    alias character varying(16),
    "user" integer,
    penalty smallint DEFAULT 0 NOT NULL,
    title character varying(64),
    created timestamp without time zone DEFAULT timezone('UTC'::text, now()),
    "runArchived" boolean DEFAULT false NOT NULL,
    "runQuick" smallint,
    "bughuntIgnore" boolean DEFAULT false NOT NULL
);


ALTER TABLE public.input OWNER TO postgres;

--
-- Name: result; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result (
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


ALTER TABLE public.result OWNER TO postgres;

--
-- Name: inputLastResult; Type: MATERIALIZED VIEW; Schema: public; Owner: postgres
--

CREATE MATERIALIZED VIEW public."inputLastResult" AS
 SELECT result.input,
    max(result.created) AS max
   FROM public.result
  GROUP BY result.input
  ORDER BY result.input DESC
  WITH NO DATA;


ALTER TABLE public."inputLastResult" OWNER TO postgres;

--
-- Name: input_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.input_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.input_id_seq OWNER TO postgres;

--
-- Name: input_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.input_id_seq OWNED BY public.input.id;


--
-- Name: input_src; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.input_src (
    input integer NOT NULL,
    raw bytea
);


ALTER TABLE public.input_src OWNER TO postgres;

--
-- Name: output; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.output (
    hash character(28) NOT NULL,
    raw bytea NOT NULL,
    id integer NOT NULL
);


ALTER TABLE public.output OWNER TO postgres;

--
-- Name: output_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.output_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.output_id_seq OWNER TO postgres;

--
-- Name: output_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.output_id_seq OWNED BY public.output.id;


--
-- Name: queue; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.queue (
    input character varying(28) NOT NULL,
    version character varying(24),
    "maxPackets" integer DEFAULT 0 NOT NULL,
    "maxRuntime" integer DEFAULT 2500 NOT NULL,
    "maxOutput" integer DEFAULT 32768 NOT NULL
);


ALTER TABLE public.queue OWNER TO postgres;

--
-- Name: references_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.references_id_seq
    START WITH 1000
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.references_id_seq OWNER TO postgres;

--
-- Name: references; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public."references" (
    id integer DEFAULT nextval('public.references_id_seq'::regclass) NOT NULL,
    function character varying(64) NOT NULL,
    link character varying(128) NOT NULL,
    name character varying(96) NOT NULL,
    parent integer
);


ALTER TABLE public."references" OWNER TO postgres;

--
-- Name: result_archive; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_archive (
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
INHERITS (public.result);


ALTER TABLE public.result_archive OWNER TO postgres;

--
-- Name: result_current; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_current (
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
INHERITS (public.result);


ALTER TABLE public.result_current OWNER TO postgres;

--
-- Name: version; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.version (
    name character varying(24) NOT NULL,
    released date DEFAULT now(),
    "order" integer,
    command character varying(128) DEFAULT '/bin/php-XXX -c /etc -q'::character varying NOT NULL,
    "isHelper" boolean DEFAULT false NOT NULL,
    id smallint NOT NULL,
    eol date
);


ALTER TABLE public.version OWNER TO postgres;

--
-- Name: result_bughunt; Type: MATERIALIZED VIEW; Schema: public; Owner: postgres
--

CREATE MATERIALIZED VIEW public.result_bughunt AS
 SELECT r.input,
    r.output,
    r.version,
    r."exitCode",
    r.created,
    r."userTime",
    r."systemTime",
    r."maxMemory"
   FROM ((public.result_current r
     JOIN public.input i ON ((i.id = r.input)))
     JOIN public.version v ON ((v.id = r.version)))
  WHERE ((NOT i."bughuntIgnore") AND ((v."isHelper" = false) OR ((v.name)::text ~~ 'rfc-%'::text)) AND (v.eol > now()) AND ((now() - (v.released)::timestamp with time zone) < '1 year'::interval) AND (r.run = i.run))
  ORDER BY i.id
  WITH NO DATA;


ALTER TABLE public.result_bughunt OWNER TO postgres;

--
-- Name: submit; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.submit (
    input integer NOT NULL,
    ip inet NOT NULL,
    created timestamp without time zone DEFAULT timezone('UTC'::text, now()),
    updated timestamp without time zone,
    count integer DEFAULT 1,
    "isQuick" boolean DEFAULT false
);


ALTER TABLE public.submit OWNER TO postgres;

--
-- Name: tx_in; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tx_in (
    transaction character varying(64) NOT NULL,
    "user" integer,
    amount integer
);


ALTER TABLE public.tx_in OWNER TO postgres;

--
-- Name: COLUMN tx_in.amount; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tx_in.amount IS 'satoshis received by us';


--
-- Name: tx_out; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tx_out (
    "user" integer NOT NULL,
    product integer NOT NULL,
    amount real NOT NULL,
    script integer,
    created timestamp without time zone DEFAULT timezone('UTC'::text, now()) NOT NULL,
    product_price integer
);


ALTER TABLE public.tx_out OWNER TO postgres;

--
-- Name: COLUMN tx_out.amount; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tx_out.amount IS 'number of products bought';


--
-- Name: COLUMN tx_out.product_price; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tx_out.product_price IS 'product.price at time of spending';


--
-- Name: tx_product_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tx_product_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.tx_product_id_seq OWNER TO postgres;

--
-- Name: tx_product; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tx_product (
    id integer DEFAULT nextval('public.tx_product_id_seq'::regclass) NOT NULL,
    per_script boolean NOT NULL,
    price integer NOT NULL,
    description character varying(128) NOT NULL,
    key character varying(32)
);


ALTER TABLE public.tx_product OWNER TO postgres;

--
-- Name: user; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public."user" (
    name character varying(15) NOT NULL,
    created timestamp without time zone DEFAULT timezone('UTC'::text, now()),
    last_login timestamp without time zone,
    login_count integer DEFAULT 0 NOT NULL,
    id integer NOT NULL,
    "btcAddress" character varying(35)
);


ALTER TABLE public."user" OWNER TO postgres;

--
-- Name: user_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.user_id_seq OWNER TO postgres;

--
-- Name: user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.user_id_seq OWNED BY public."user".id;


--
-- Name: version_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.version_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.version_id_seq OWNER TO postgres;

--
-- Name: version_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.version_id_seq OWNED BY public.version.id;


--
-- Name: input id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.input ALTER COLUMN id SET DEFAULT nextval('public.input_id_seq'::regclass);


--
-- Name: output id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.output ALTER COLUMN id SET DEFAULT nextval('public.output_id_seq'::regclass);


--
-- Name: user id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."user" ALTER COLUMN id SET DEFAULT nextval('public.user_id_seq'::regclass);


--
-- Name: version id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.version ALTER COLUMN id SET DEFAULT nextval('public.version_id_seq'::regclass);


--
-- Name: assertion assertion_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.assertion
    ADD CONSTRAINT assertion_pkey PRIMARY KEY (input);


--
-- Name: result_current inputVersionRun; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result_current
    ADD CONSTRAINT "inputVersionRun" UNIQUE (input, version, run);

ALTER TABLE public.result_current CLUSTER ON "inputVersionRun";


--
-- Name: result_archive inputVersionRunArchive; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result_archive
    ADD CONSTRAINT "inputVersionRunArchive" UNIQUE (input, version, run);

ALTER TABLE public.result_archive CLUSTER ON "inputVersionRunArchive";


--
-- Name: input input_hash_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.input
    ADD CONSTRAINT input_hash_key UNIQUE (hash);


--
-- Name: input input_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.input
    ADD CONSTRAINT input_pkey PRIMARY KEY (id);

ALTER TABLE public.input CLUSTER ON input_pkey;


--
-- Name: input input_short_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.input
    ADD CONSTRAINT input_short_key UNIQUE (short);


--
-- Name: input_src input_src_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.input_src
    ADD CONSTRAINT input_src_pkey PRIMARY KEY (input);

ALTER TABLE public.input_src CLUSTER ON input_src_pkey;


--
-- Name: output output_hash; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.output
    ADD CONSTRAINT output_hash UNIQUE (hash);


--
-- Name: output output_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.output
    ADD CONSTRAINT output_pkey PRIMARY KEY (id);


--
-- Name: references references_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."references"
    ADD CONSTRAINT references_pkey PRIMARY KEY (id);

ALTER TABLE public."references" CLUSTER ON references_pkey;


--
-- Name: submit submit_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.submit
    ADD CONSTRAINT submit_pkey PRIMARY KEY (input, ip);


--
-- Name: tx_in tx_in_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tx_in
    ADD CONSTRAINT tx_in_pkey PRIMARY KEY (transaction);


--
-- Name: tx_product tx_product_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tx_product
    ADD CONSTRAINT tx_product_key UNIQUE (key);


--
-- Name: tx_product tx_products_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tx_product
    ADD CONSTRAINT tx_products_pkey PRIMARY KEY (id);

ALTER TABLE public.tx_product CLUSTER ON tx_products_pkey;


--
-- Name: user user_name; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."user"
    ADD CONSTRAINT user_name UNIQUE (name);


--
-- Name: user user_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."user"
    ADD CONSTRAINT user_pkey PRIMARY KEY (id);


--
-- Name: version version_name; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.version
    ADD CONSTRAINT version_name UNIQUE (name);


--
-- Name: version version_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.version
    ADD CONSTRAINT version_pkey PRIMARY KEY (id);

ALTER TABLE public.version CLUSTER ON version_pkey;


--
-- Name: functionCall_input; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "functionCall_input" ON public."functionCall" USING btree (input);


--
-- Name: inputAlias; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "inputAlias" ON public.input USING btree (alias);


--
-- Name: inputLastResult_pkey; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX "inputLastResult_pkey" ON public."inputLastResult" USING btree (input);


--
-- Name: input_bhignore; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX input_bhignore ON public.input USING btree ("bughuntIgnore");


--
-- Name: resultBughunt; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX "resultBughunt" ON public.result_bughunt USING btree (input, version);


--
-- Name: result_exitCode; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_exitCode" ON public.result_current USING brin ("exitCode");


--
-- Name: submit_penaltylookup; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX submit_penaltylookup ON public.submit USING btree (ip);


--
-- Name: input input_runarchived_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER input_runarchived_trigger AFTER UPDATE OF "runArchived" ON public.input FOR EACH ROW EXECUTE PROCEDURE public.input_runarchived_clean();


--
-- Name: result insert_result_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER insert_result_trigger BEFORE INSERT ON public.result FOR EACH ROW EXECUTE PROCEDURE public.result_insert_trigger();


--
-- Name: queue queue_insert_notify; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER queue_insert_notify AFTER INSERT ON public.queue FOR EACH STATEMENT EXECUTE PROCEDURE public.notify_daemon();


--
-- Name: assertion assertion_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.assertion
    ADD CONSTRAINT assertion_input_fkey FOREIGN KEY (input) REFERENCES public.input(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: assertion assertion_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.assertion
    ADD CONSTRAINT assertion_user_fkey FOREIGN KEY ("user") REFERENCES public."user"(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: functionCall functionCall_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."functionCall"
    ADD CONSTRAINT "functionCall_input_fkey" FOREIGN KEY (input) REFERENCES public.input(id);


--
-- Name: input input_runQuick_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.input
    ADD CONSTRAINT "input_runQuick_fkey" FOREIGN KEY ("runQuick") REFERENCES public.version(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: input input_source_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.input
    ADD CONSTRAINT input_source_fkey FOREIGN KEY (source) REFERENCES public.input(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: input_src input_src_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.input_src
    ADD CONSTRAINT input_src_input_fkey FOREIGN KEY (input) REFERENCES public.input(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: input input_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.input
    ADD CONSTRAINT input_user_fkey FOREIGN KEY ("user") REFERENCES public."user"(id) ON UPDATE RESTRICT ON DELETE SET NULL;


--
-- Name: queue queue_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.queue
    ADD CONSTRAINT queue_input_fkey FOREIGN KEY (input) REFERENCES public.input(short) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: queue queue_version_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.queue
    ADD CONSTRAINT queue_version_fkey FOREIGN KEY (version) REFERENCES public.version(name) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: references references_parent_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."references"
    ADD CONSTRAINT references_parent_fkey FOREIGN KEY (parent) REFERENCES public."references"(id) ON UPDATE RESTRICT ON DELETE SET NULL;


--
-- Name: result_archive result_archive_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result_archive
    ADD CONSTRAINT result_archive_input_fkey FOREIGN KEY (input) REFERENCES public.input(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: result_archive result_archive_output_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result_archive
    ADD CONSTRAINT result_archive_output_fkey FOREIGN KEY (output) REFERENCES public.output(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: result_archive result_archive_version_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result_archive
    ADD CONSTRAINT result_archive_version_fkey FOREIGN KEY (version) REFERENCES public.version(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: result_current result_current_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result_current
    ADD CONSTRAINT result_current_input_fkey FOREIGN KEY (input) REFERENCES public.input(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: result_current result_current_output_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result_current
    ADD CONSTRAINT result_current_output_fkey FOREIGN KEY (output) REFERENCES public.output(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: result_current result_current_version_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result_current
    ADD CONSTRAINT result_current_version_fkey FOREIGN KEY (version) REFERENCES public.version(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: submit submit_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.submit
    ADD CONSTRAINT submit_input_fkey FOREIGN KEY (input) REFERENCES public.input(id) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: tx_in tx_in_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tx_in
    ADD CONSTRAINT tx_in_user_fkey FOREIGN KEY ("user") REFERENCES public."user"(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: tx_out tx_out_product_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tx_out
    ADD CONSTRAINT tx_out_product_fkey FOREIGN KEY (product) REFERENCES public.tx_product(id) ON UPDATE RESTRICT ON DELETE SET NULL;


--
-- Name: tx_out tx_out_script_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tx_out
    ADD CONSTRAINT tx_out_script_fkey FOREIGN KEY (script) REFERENCES public.input(id) ON UPDATE RESTRICT ON DELETE SET NULL;


--
-- Name: tx_out tx_out_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tx_out
    ADD CONSTRAINT tx_out_user_fkey FOREIGN KEY ("user") REFERENCES public."user"(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO PUBLIC;


--
-- Name: TABLE assertion; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT ON TABLE public.assertion TO website;


--
-- Name: TABLE "functionCall"; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,DELETE ON TABLE public."functionCall" TO website;


--
-- Name: TABLE input; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,UPDATE ON TABLE public.input TO website;
GRANT SELECT ON TABLE public.input TO daemon;


--
-- Name: COLUMN input.state; Type: ACL; Schema: public; Owner: postgres
--

GRANT UPDATE(state) ON TABLE public.input TO daemon;


--
-- Name: COLUMN input.run; Type: ACL; Schema: public; Owner: postgres
--

GRANT UPDATE(run) ON TABLE public.input TO daemon;


--
-- Name: COLUMN input."operationCount"; Type: ACL; Schema: public; Owner: postgres
--

GRANT UPDATE("operationCount") ON TABLE public.input TO website;


--
-- Name: COLUMN input.penalty; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT(penalty),UPDATE(penalty) ON TABLE public.input TO daemon;


--
-- Name: COLUMN input."runArchived"; Type: ACL; Schema: public; Owner: postgres
--

GRANT UPDATE("runArchived") ON TABLE public.input TO website;


--
-- Name: COLUMN input."runQuick"; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT("runQuick"),INSERT("runQuick"),UPDATE("runQuick") ON TABLE public.input TO website;


--
-- Name: TABLE result; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON TABLE public.result TO website;
GRANT SELECT,INSERT,DELETE ON TABLE public.result TO daemon;


--
-- Name: TABLE "inputLastResult"; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,DELETE ON TABLE public."inputLastResult" TO website;


--
-- Name: SEQUENCE input_id_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,USAGE ON SEQUENCE public.input_id_seq TO website;


--
-- Name: TABLE input_src; Type: ACL; Schema: public; Owner: postgres
--

REVOKE ALL ON TABLE public.input_src FROM postgres;
GRANT SELECT,INSERT,REFERENCES,TRIGGER,TRUNCATE ON TABLE public.input_src TO postgres;
GRANT SELECT,INSERT ON TABLE public.input_src TO website;
GRANT SELECT ON TABLE public.input_src TO daemon;


--
-- Name: TABLE output; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT ON TABLE public.output TO daemon;
GRANT SELECT ON TABLE public.output TO website;


--
-- Name: SEQUENCE output_id_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,USAGE ON SEQUENCE public.output_id_seq TO daemon;


--
-- Name: TABLE queue; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,DELETE ON TABLE public.queue TO daemon;
GRANT SELECT,INSERT ON TABLE public.queue TO website;


--
-- Name: SEQUENCE references_id_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,USAGE ON SEQUENCE public.references_id_seq TO website;


--
-- Name: TABLE "references"; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON TABLE public."references" TO website;


--
-- Name: TABLE result_archive; Type: ACL; Schema: public; Owner: postgres
--

GRANT INSERT,DELETE ON TABLE public.result_archive TO daemon;


--
-- Name: TABLE result_current; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,DELETE ON TABLE public.result_current TO daemon;
GRANT SELECT ON TABLE public.result_current TO website;


--
-- Name: TABLE version; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON TABLE public.version TO website;
GRANT SELECT ON TABLE public.version TO daemon;


--
-- Name: TABLE result_bughunt; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON TABLE public.result_bughunt TO website;


--
-- Name: TABLE submit; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,UPDATE ON TABLE public.submit TO website;
GRANT SELECT ON TABLE public.submit TO daemon;


--
-- Name: TABLE tx_in; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON TABLE public.tx_in TO website;


--
-- Name: TABLE tx_out; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT ON TABLE public.tx_out TO website;


--
-- Name: TABLE tx_product; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON TABLE public.tx_product TO website;


--
-- Name: TABLE "user"; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,UPDATE ON TABLE public."user" TO website;


--
-- Name: SEQUENCE user_id_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON SEQUENCE public.user_id_seq TO website;


--
-- PostgreSQL database dump complete
--