/* sudo -u postgres pg_dump phpshell -s */
--
-- PostgreSQL database dump
--

\restrict JpE6RKBrb4BM5yPRF7j50hFqaBcKPte6CMa3KjDlNnD7I2lUraQgdKCGVbhuImu

-- Dumped from database version 18.6
-- Dumped by pg_dump version 18.6

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: public; Type: SCHEMA; Schema: -; Owner: postgres
--

-- *not* creating schema, since initdb creates it


ALTER SCHEMA public OWNER TO postgres;

--
-- Name: pg_repack; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_repack WITH SCHEMA public;


--
-- Name: EXTENSION pg_repack; Type: COMMENT; Schema: -; Owner:
--

COMMENT ON EXTENSION pg_repack IS 'Reorganize tables in PostgreSQL databases with minimal locks';


--
-- Name: pg_stat_statements; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_stat_statements WITH SCHEMA public;


--
-- Name: EXTENSION pg_stat_statements; Type: COMMENT; Schema: -; Owner:
--

COMMENT ON EXTENSION pg_stat_statements IS 'track execution statistics of all SQL statements executed';


--
-- Name: input_mutated(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.input_mutated() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN UPDATE input SET "lastResultChange" = TIMEZONE('UTC'::text, NOW()) WHERE id=NEW.input; RETURN NEW; END; $$;


ALTER FUNCTION public.input_mutated() OWNER TO postgres;

--
-- Name: notify_daemon(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.notify_daemon() RETURNS trigger
    LANGUAGE plpgsql
    AS $$BEGIN
PERFORM pg_notify('daemon', TG_ARGV[0]);
RETURN NEW;
END;
$$;


ALTER FUNCTION public.notify_daemon() OWNER TO postgres;

--
-- Name: result_covers(integer, integer); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.result_covers(pid integer, porder integer) RETURNS boolean
    LANGUAGE sql STABLE PARALLEL SAFE
    AS $$
    SELECT EXISTS (
        SELECT 1 FROM result_new r
        WHERE r.input = pid
          AND porder BETWEEN r."minVersion" AND r."maxVersion"
    )
$$;


ALTER FUNCTION public.result_covers(pid integer, porder integer) OWNER TO postgres;

SET default_tablespace = '';

SET default_table_access_method = heap;

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
-- Name: function_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.function_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.function_id_seq OWNER TO postgres;

--
-- Name: function; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.function (
    id integer DEFAULT nextval('public.function_id_seq'::regclass) NOT NULL,
    text character varying(64) NOT NULL,
    source character varying(64) NOT NULL,
    popularity integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.function OWNER TO postgres;

--
-- Name: functionCall; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public."functionCall" (
    input integer NOT NULL,
    function integer NOT NULL
);


ALTER TABLE public."functionCall" OWNER TO postgres;

--
-- Name: helper_output; Type: TABLE; Schema: public; Owner: postgres
--

CREATE UNLOGGED TABLE public.helper_output (
    input integer NOT NULL,
    helper character varying(8) NOT NULL,
    output bytea NOT NULL
);


ALTER TABLE public.helper_output OWNER TO postgres;

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
    "operationCount" smallint,
    alias character varying(16),
    "user" integer,
    penalty smallint DEFAULT 0 NOT NULL,
    title character varying(64),
    created timestamp without time zone DEFAULT timezone('UTC'::text, now()),
    "runArchived" boolean DEFAULT false NOT NULL,
    "bughuntIgnore" boolean DEFAULT false NOT NULL,
    "lastResultChange" timestamp without time zone
)
WITH (fillfactor='75', autovacuum_vacuum_cost_delay='5', autovacuum_vacuum_cost_limit='1000');


ALTER TABLE public.input OWNER TO postgres;

--
-- Name: input_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.input_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.input_id_seq OWNER TO postgres;

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
    id integer NOT NULL,
    "exitCode" smallint DEFAULT 0 NOT NULL
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


ALTER SEQUENCE public.output_id_seq OWNER TO postgres;

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


ALTER SEQUENCE public.references_id_seq OWNER TO postgres;

--
-- Name: result_new; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_new (
    input integer NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint NOT NULL,
    "minVersion" smallint NOT NULL,
    "maxVersion" smallint NOT NULL,
    "avgUserTime" real NOT NULL,
    "avgMaxMemory" integer NOT NULL,
    runs smallint DEFAULT 1 NOT NULL,
    stable boolean DEFAULT true NOT NULL
);


ALTER TABLE public.result_new OWNER TO postgres;

--
-- Name: version; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.version (
    name character varying(24) NOT NULL,
    released date DEFAULT now(),
    "order" integer NOT NULL,
    command character varying(254) DEFAULT '/bin/php-XXX -c /etc -q'::character varying NOT NULL,
    "isHelper" boolean DEFAULT false NOT NULL,
    id smallint NOT NULL,
    eol date
);


ALTER TABLE public.version OWNER TO postgres;

--
-- Name: version_forBughunt; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public."version_forBughunt" AS
 SELECT name,
    released,
    "order",
    command,
    "isHelper",
    id,
    eol
   FROM public.version
  WHERE ((now() - (released)::timestamp with time zone) < '2 mons'::interval);


ALTER VIEW public."version_forBughunt" OWNER TO postgres;

--
-- Name: result_bughunt; Type: MATERIALIZED VIEW; Schema: public; Owner: postgres
--

CREATE MATERIALIZED VIEW public.result_bughunt AS
 WITH r AS (
         SELECT rn.input,
            rn.output,
            rn."exitCode",
            rn."minVersion",
            rn."maxVersion",
            rn."avgUserTime",
            rn."avgMaxMemory",
            rn.runs,
            rn.stable
           FROM public.result_new rn
          WHERE (EXISTS ( SELECT 1
                   FROM public."version_forBughunt" vb
                  WHERE ((vb."order" >= rn."minVersion") AND (vb."order" <= rn."maxVersion"))))
        )
 SELECT input,
    output,
    "exitCode",
    "minVersion",
    "maxVersion",
    "avgUserTime",
    "avgMaxMemory",
    runs,
    stable
   FROM r
  WHERE (input IN ( SELECT r2.input
           FROM (r r2
             JOIN public.input i ON ((i.id = r2.input)))
          WHERE (NOT i."bughuntIgnore")
          GROUP BY r2.input
         HAVING (count(DISTINCT r2.output) > 1)))
  WITH NO DATA;
ALTER TABLE ONLY public.result_bughunt ALTER COLUMN input SET STATISTICS 800;
ALTER TABLE ONLY public.result_bughunt ALTER COLUMN "minVersion" SET STATISTICS 800;


ALTER MATERIALIZED VIEW public.result_bughunt OWNER TO postgres;

--
-- Name: submit; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.submit (
    input integer NOT NULL,
    ip inet NOT NULL,
    created timestamp without time zone DEFAULT timezone('UTC'::text, now()),
    updated timestamp without time zone,
    count integer DEFAULT 1 NOT NULL,
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


ALTER SEQUENCE public.tx_product_id_seq OWNER TO postgres;

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


ALTER SEQUENCE public.user_id_seq OWNER TO postgres;

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


ALTER SEQUENCE public.version_id_seq OWNER TO postgres;

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
-- Name: functionCall functionCall_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."functionCall"
    ADD CONSTRAINT "functionCall_pkey" PRIMARY KEY (input, function);

ALTER TABLE public."functionCall" CLUSTER ON "functionCall_pkey";


--
-- Name: function function_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.function
    ADD CONSTRAINT function_pkey PRIMARY KEY (id);


--
-- Name: function function_text; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.function
    ADD CONSTRAINT function_text UNIQUE (text);


--
-- Name: input inputHashes; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.input
    ADD CONSTRAINT "inputHashes" UNIQUE (hash);


--
-- Name: input inputShorts; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.input
    ADD CONSTRAINT "inputShorts" UNIQUE (short);


--
-- Name: input input_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.input
    ADD CONSTRAINT input_pkey PRIMARY KEY (id);

ALTER TABLE public.input CLUSTER ON input_pkey;


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
-- Name: result_new result_new_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result_new
    ADD CONSTRAINT result_new_pkey PRIMARY KEY (input, output, "exitCode", "minVersion");


--
-- Name: submit submit_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.submit
    ADD CONSTRAINT submit_pkey PRIMARY KEY (ip, input);


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
-- Name: version version_order_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.version
    ADD CONSTRAINT version_order_key UNIQUE ("order");


--
-- Name: version version_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.version
    ADD CONSTRAINT version_pkey PRIMARY KEY (id);

ALTER TABLE public.version CLUSTER ON version_pkey;


--
-- Name: functionCallSearch; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "functionCallSearch" ON public."functionCall" USING btree (function);


--
-- Name: inputAlias; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "inputAlias" ON public.input USING btree (alias);


--
-- Name: inputBughuntIgnore; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "inputBughuntIgnore" ON public.input USING btree ("bughuntIgnore") WHERE (NOT "bughuntIgnore");


--
-- Name: inputsPending; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "inputsPending" ON public.input USING btree (state) WHERE (NOT (((state)::text = 'done'::text) OR ((state)::text = 'abusive'::text)));


--
-- Name: result_new_cover; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX result_new_cover ON public.result_new USING btree (input, "minVersion", "maxVersion");


--
-- Name: submitLast; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "submitLast" ON public.submit USING btree (input);

ALTER TABLE public.submit CLUSTER ON "submitLast";


--
-- Name: submitRecent; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "submitRecent" ON public.submit USING btree (ip) WHERE (created > '2026-08-01 00:00:00'::timestamp without time zone);


--
-- Name: version_order; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX version_order ON public.version USING btree ("order");


--
-- Name: queue queue_insert_notify; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER queue_insert_notify AFTER INSERT ON public.queue FOR EACH ROW EXECUTE FUNCTION public.notify_daemon('queue');


--
-- Name: version version_update_notify; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER version_update_notify AFTER INSERT OR DELETE OR UPDATE ON public.version FOR EACH STATEMENT EXECUTE FUNCTION public.notify_daemon('version');


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
-- Name: functionCall functionCall_function_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."functionCall"
    ADD CONSTRAINT "functionCall_function_fkey" FOREIGN KEY (function) REFERENCES public.function(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: functionCall functionCall_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public."functionCall"
    ADD CONSTRAINT "functionCall_input_fkey" FOREIGN KEY (input) REFERENCES public.input(id) ON DELETE CASCADE;


--
-- Name: helper_output helper_output_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.helper_output
    ADD CONSTRAINT helper_output_input_fkey FOREIGN KEY (input) REFERENCES public.input(id);


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
-- Name: result_new result_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result_new
    ADD CONSTRAINT result_input_fkey FOREIGN KEY (input) REFERENCES public.input(id) ON DELETE CASCADE;


--
-- Name: result_new result_maxVersion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result_new
    ADD CONSTRAINT "result_maxVersion_fkey" FOREIGN KEY ("maxVersion") REFERENCES public.version("order");


--
-- Name: result_new result_minVersion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result_new
    ADD CONSTRAINT "result_minVersion_fkey" FOREIGN KEY ("minVersion") REFERENCES public.version("order");


--
-- Name: result_new result_output_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result_new
    ADD CONSTRAINT result_output_fkey FOREIGN KEY (output) REFERENCES public.output(id);


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
-- Name: TABLE assertion; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT ON TABLE public.assertion TO website;


--
-- Name: SEQUENCE function_id_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,USAGE ON SEQUENCE public.function_id_seq TO website;


--
-- Name: TABLE function; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,UPDATE ON TABLE public.function TO website;


--
-- Name: TABLE "functionCall"; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,DELETE ON TABLE public."functionCall" TO website;


--
-- Name: TABLE helper_output; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,DELETE ON TABLE public.helper_output TO website;
GRANT INSERT ON TABLE public.helper_output TO daemon;


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
-- Name: COLUMN input."lastResultChange"; Type: ACL; Schema: public; Owner: postgres
--

GRANT UPDATE("lastResultChange") ON TABLE public.input TO daemon;


--
-- Name: SEQUENCE input_id_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,USAGE ON SEQUENCE public.input_id_seq TO website;


--
-- Name: TABLE input_src; Type: ACL; Schema: public; Owner: postgres
--

REVOKE ALL ON TABLE public.input_src FROM postgres;
GRANT SELECT,INSERT,REFERENCES,TRIGGER,TRUNCATE,MAINTAIN ON TABLE public.input_src TO postgres;
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
-- Name: TABLE result_new; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.result_new TO daemon;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.result_new TO website;


--
-- Name: TABLE version; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON TABLE public.version TO website;
GRANT SELECT ON TABLE public.version TO daemon;


--
-- Name: TABLE "version_forBughunt"; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON TABLE public."version_forBughunt" TO PUBLIC;


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

\unrestrict JpE6RKBrb4BM5yPRF7j50hFqaBcKPte6CMa3KjDlNnD7I2lUraQgdKCGVbhuImu
