/* sudo -u postgres pg_dump phpshell -s */
--
-- PostgreSQL database dump
--

-- Dumped from database version 15.4
-- Dumped by pg_dump version 15.4

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
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


ALTER TABLE public.function_id_seq OWNER TO postgres;

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
);


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
-- Name: result; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result (
    input integer NOT NULL,
    version smallint NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint DEFAULT 0 NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    runs smallint DEFAULT 1 NOT NULL,
    mutations smallint DEFAULT 0 NOT NULL
)
PARTITION BY LIST (version);


ALTER TABLE public.result OWNER TO postgres;

--
-- Name: version; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.version (
    name character varying(24) NOT NULL,
    released date DEFAULT now(),
    "order" integer,
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
 SELECT version.name,
    version.released,
    version."order",
    version.command,
    version."isHelper",
    version.id,
    version.eol
   FROM public.version
  WHERE ((now() - (version.released)::timestamp with time zone) < '2 mons'::interval);


ALTER TABLE public."version_forBughunt" OWNER TO postgres;

--
-- Name: result_bughunt; Type: MATERIALIZED VIEW; Schema: public; Owner: postgres
--

CREATE MATERIALIZED VIEW public.result_bughunt AS
 WITH r AS (
         SELECT result.input,
            result.version,
            result.output,
            result."exitCode",
            result."userTime",
            result."systemTime",
            result."maxMemory",
            result.runs,
            result.mutations
           FROM public.result
          WHERE (result.version IN ( SELECT "version_forBughunt".id
                   FROM public."version_forBughunt"))
        )
 SELECT r.input,
    r.version,
    r.output,
    r."exitCode",
    r."userTime",
    r."systemTime",
    r."maxMemory",
    r.runs,
    r.mutations
   FROM r
  WHERE (r.input IN ( SELECT input.id
           FROM (r r1
             JOIN public.input ON ((input.id = r1.input)))
          WHERE (NOT input."bughuntIgnore")
          GROUP BY input.id
         HAVING (count(DISTINCT r1.output) > 1)))
  WITH NO DATA;
ALTER TABLE ONLY public.result_bughunt ALTER COLUMN input SET STATISTICS 800;
ALTER TABLE ONLY public.result_bughunt ALTER COLUMN version SET STATISTICS 800;


ALTER TABLE public.result_bughunt OWNER TO postgres;

--
-- Name: result_helper; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_helper (
    input integer NOT NULL,
    version smallint NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint DEFAULT 0 NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    runs smallint DEFAULT 1 NOT NULL,
    mutations smallint DEFAULT 0 NOT NULL
);


ALTER TABLE public.result_helper OWNER TO postgres;

--
-- Name: result_php4; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_php4 (
    input integer NOT NULL,
    version smallint NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint DEFAULT 0 NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    runs smallint DEFAULT 1 NOT NULL,
    mutations smallint DEFAULT 0 NOT NULL
);


ALTER TABLE public.result_php4 OWNER TO postgres;

--
-- Name: result_php53; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_php53 (
    input integer NOT NULL,
    version smallint NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint DEFAULT 0 NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    runs smallint DEFAULT 1 NOT NULL,
    mutations smallint DEFAULT 0 NOT NULL
);


ALTER TABLE public.result_php53 OWNER TO postgres;

--
-- Name: result_php54; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_php54 (
    input integer NOT NULL,
    version smallint NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint DEFAULT 0 NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    runs smallint DEFAULT 1 NOT NULL,
    mutations smallint DEFAULT 0 NOT NULL
);


ALTER TABLE public.result_php54 OWNER TO postgres;

--
-- Name: result_php55; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_php55 (
    input integer NOT NULL,
    version smallint NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint DEFAULT 0 NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    runs smallint DEFAULT 1 NOT NULL,
    mutations smallint DEFAULT 0 NOT NULL
);


ALTER TABLE public.result_php55 OWNER TO postgres;

--
-- Name: result_php56; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_php56 (
    input integer NOT NULL,
    version smallint NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint DEFAULT 0 NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    runs smallint DEFAULT 1 NOT NULL,
    mutations smallint DEFAULT 0 NOT NULL
);


ALTER TABLE public.result_php56 OWNER TO postgres;

--
-- Name: result_php5x; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_php5x (
    input integer NOT NULL,
    version smallint NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint DEFAULT 0 NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    runs smallint DEFAULT 1 NOT NULL,
    mutations smallint DEFAULT 0 NOT NULL
);


ALTER TABLE public.result_php5x OWNER TO postgres;

--
-- Name: result_php70; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_php70 (
    input integer NOT NULL,
    version smallint NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint DEFAULT 0 NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    runs smallint DEFAULT 1 NOT NULL,
    mutations smallint DEFAULT 0 NOT NULL
);


ALTER TABLE public.result_php70 OWNER TO postgres;

--
-- Name: result_php71; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_php71 (
    input integer NOT NULL,
    version smallint NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint DEFAULT 0 NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    runs smallint DEFAULT 1 NOT NULL,
    mutations smallint DEFAULT 0 NOT NULL
);


ALTER TABLE public.result_php71 OWNER TO postgres;

--
-- Name: result_php72; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_php72 (
    input integer NOT NULL,
    version smallint NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint DEFAULT 0 NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    runs smallint DEFAULT 1 NOT NULL,
    mutations smallint DEFAULT 0 NOT NULL
);


ALTER TABLE public.result_php72 OWNER TO postgres;

--
-- Name: result_php73; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_php73 (
    input integer NOT NULL,
    version smallint NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint DEFAULT 0 NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    runs smallint DEFAULT 1 NOT NULL,
    mutations smallint DEFAULT 0 NOT NULL
);


ALTER TABLE public.result_php73 OWNER TO postgres;

--
-- Name: result_php74; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_php74 (
    input integer NOT NULL,
    version smallint NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint DEFAULT 0 NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    runs smallint DEFAULT 1 NOT NULL,
    mutations smallint DEFAULT 0 NOT NULL
);


ALTER TABLE public.result_php74 OWNER TO postgres;

--
-- Name: result_php80; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_php80 (
    input integer NOT NULL,
    version smallint NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint DEFAULT NULL NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    runs smallint DEFAULT NULL NOT NULL,
    mutations smallint DEFAULT NULL NOT NULL
);


ALTER TABLE public.result_php80 OWNER TO postgres;

--
-- Name: result_php81; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_php81 (
    input integer NOT NULL,
    version smallint NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint DEFAULT 0 NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    runs smallint DEFAULT 1 NOT NULL,
    mutations smallint DEFAULT 0 NOT NULL
);


ALTER TABLE public.result_php81 OWNER TO postgres;

--
-- Name: result_php82; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_php82 (
    input integer NOT NULL,
    version smallint NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint DEFAULT 0 NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    runs smallint DEFAULT 1 NOT NULL,
    mutations smallint DEFAULT 0 NOT NULL
);


ALTER TABLE public.result_php82 OWNER TO postgres;

--
-- Name: result_rfc; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.result_rfc (
    input integer NOT NULL,
    version smallint NOT NULL,
    output integer NOT NULL,
    "exitCode" smallint DEFAULT 0 NOT NULL,
    "userTime" real NOT NULL,
    "systemTime" real NOT NULL,
    "maxMemory" integer NOT NULL,
    runs smallint DEFAULT 1 NOT NULL,
    mutations smallint DEFAULT 0 NOT NULL
);


ALTER TABLE public.result_rfc OWNER TO postgres;

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
-- Name: result_helper; Type: TABLE ATTACH; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result ATTACH PARTITION public.result_helper FOR VALUES IN ('1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16');


--
-- Name: result_php4; Type: TABLE ATTACH; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result ATTACH PARTITION public.result_php4 FOR VALUES IN ('32', '33', '34', '35', '36', '37', '38', '39', '40', '43', '45', '47', '49', '51', '54', '58', '60', '64', '65', '66', '71', '73');


--
-- Name: result_php53; Type: TABLE ATTACH; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result ATTACH PARTITION public.result_php53 FOR VALUES IN ('78', '80', '83', '85', '87', '90', '91', '92', '93', '94', '95', '98', '100', '101', '104', '106', '107', '110', '112', '113', '116', '118', '119', '121', '123', '126', '128', '131', '143', '162');


--
-- Name: result_php54; Type: TABLE ATTACH; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result ATTACH PARTITION public.result_php54 FOR VALUES IN ('96', '97', '99', '102', '103', '105', '108', '109', '111', '114', '115', '117', '120', '122', '124', '125', '127', '130', '133', '135', '138', '140', '141', '145', '146', '149', '151', '153', '155', '157', '158', '161', '163', '168', '172', '175', '177', '181', '183', '186', '190', '194', '197', '205', '212', '372');


--
-- Name: result_php55; Type: TABLE ATTACH; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result ATTACH PARTITION public.result_php55 FOR VALUES IN ('129', '132', '134', '136', '137', '139', '142', '144', '147', '148', '150', '152', '154', '156', '159', '160', '164', '169', '170', '174', '178', '179', '182', '187', '189', '192', '200', '204', '209', '214', '231', '234', '238', '241', '245', '248', '254', '258', '371');


--
-- Name: result_php56; Type: TABLE ATTACH; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result ATTACH PARTITION public.result_php56 FOR VALUES IN ('165', '166', '167', '171', '173', '176', '180', '184', '188', '191', '193', '198', '203', '210', '216', '221', '225', '230', '233', '239', '242', '244', '247', '253', '259', '263', '268', '272', '276', '282', '285', '380', '381', '382', '383', '384', '385', '386', '387', '410', '411');


--
-- Name: result_php5x; Type: TABLE ATTACH; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result ATTACH PARTITION public.result_php5x FOR VALUES IN ('41', '42', '44', '46', '48', '50', '52', '53', '55', '56', '57', '59', '61', '62', '63', '67', '68', '69', '70', '72', '74', '75', '76', '77', '79', '81', '82', '84', '86', '88', '89');


--
-- Name: result_php70; Type: TABLE ATTACH; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result ATTACH PARTITION public.result_php70 FOR VALUES IN ('226', '228', '229', '232', '237', '240', '243', '246', '251', '256', '262', '267', '271', '275', '281', '286', '297', '302', '304', '306', '309', '325', '326', '327', '330', '337', '341', '344', '352', '354', '361', '370', '396', '400');


--
-- Name: result_php71; Type: TABLE ATTACH; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result ATTACH PARTITION public.result_php71 FOR VALUES IN ('280', '283', '295', '301', '303', '307', '308', '313', '316', '323', '329', '336', '340', '345', '346', '349', '351', '355', '362', '363', '369', '374', '378', '391', '398', '401', '412', '413', '416', '421', '422', '431', '436', '445');


--
-- Name: result_php72; Type: TABLE ATTACH; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result ATTACH PARTITION public.result_php72 FOR VALUES IN ('342', '343', '347', '348', '350', '353', '356', '360', '364', '373', '377', '392', '395', '402', '406', '407', '409', '415', '418', '420', '426', '430', '435', '440', '444', '449', '453', '456', '459', '462', '467', '468', '474', '477', '488');


--
-- Name: result_php73; Type: TABLE ATTACH; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result ATTACH PARTITION public.result_php73 FOR VALUES IN ('403', '404', '405', '408', '414', '417', '419', '425', '429', '434', '439', '443', '448', '452', '455', '458', '461', '464', '466', '470', '473', '479', '482', '485', '493', '498', '501', '504', '509', '516', '521', '526', '532', '533');


--
-- Name: result_php74; Type: TABLE ATTACH; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result ATTACH PARTITION public.result_php74 FOR VALUES IN ('450', '451', '454', '457', '460', '463', '465', '469', '472', '478', '483', '486', '494', '499', '502', '505', '507', '512', '514', '515', '517', '519', '522', '527', '530', '534', '537', '542', '547', '552', '567', '570');


--
-- Name: result_php80; Type: TABLE ATTACH; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result ATTACH PARTITION public.result_php80 FOR VALUES IN ('500', '503', '506', '508', '510', '511', '513', '518', '520', '523', '528', '531', '535', '539', '540', '543', '545', '549', '551', '554', '556', '558', '560', '563', '569', '572', '580', '586', '595', '600');


--
-- Name: result_php81; Type: TABLE ATTACH; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result ATTACH PARTITION public.result_php81 FOR VALUES IN ('536', '538', '541', '544', '546', '548', '550', '553', '555', '557', '559', '562', '568', '571', '581', '583', '585', '588', '590', '592', '594', '597', '599', '602', '603');


--
-- Name: result_php82; Type: TABLE ATTACH; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result ATTACH PARTITION public.result_php82 FOR VALUES IN ('578', '579', '582', '584', '587', '589', '591', '593', '596', '598', '601', '604');


--
-- Name: result_rfc; Type: TABLE ATTACH; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.result ATTACH PARTITION public.result_rfc FOR VALUES IN ('17', '18');


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
-- Name: resultBughuntVersion; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "resultBughuntVersion" ON public.result_bughunt USING btree (version);


--
-- Name: resultExitCode; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "resultExitCode" ON ONLY public.result USING brin ("exitCode");


--
-- Name: resultInput; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "resultInput" ON ONLY public.result USING btree (input);


--
-- Name: result_helper_exitCode_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_helper_exitCode_idx" ON public.result_helper USING brin ("exitCode");


--
-- Name: result_helper_input_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX result_helper_input_idx ON public.result_helper USING btree (input);


--
-- Name: result_php4_exitCode_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_php4_exitCode_idx" ON public.result_php4 USING brin ("exitCode");


--
-- Name: result_php4_input_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX result_php4_input_idx ON public.result_php4 USING btree (input);

ALTER TABLE public.result_php4 CLUSTER ON result_php4_input_idx;


--
-- Name: result_php53_exitCode_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_php53_exitCode_idx" ON public.result_php53 USING brin ("exitCode");


--
-- Name: result_php53_input_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX result_php53_input_idx ON public.result_php53 USING btree (input);

ALTER TABLE public.result_php53 CLUSTER ON result_php53_input_idx;


--
-- Name: result_php54_exitCode_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_php54_exitCode_idx" ON public.result_php54 USING brin ("exitCode");


--
-- Name: result_php54_input_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX result_php54_input_idx ON public.result_php54 USING btree (input);

ALTER TABLE public.result_php54 CLUSTER ON result_php54_input_idx;


--
-- Name: result_php55_exitCode_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_php55_exitCode_idx" ON public.result_php55 USING brin ("exitCode");


--
-- Name: result_php55_input_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX result_php55_input_idx ON public.result_php55 USING btree (input);

ALTER TABLE public.result_php55 CLUSTER ON result_php55_input_idx;


--
-- Name: result_php56_exitCode_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_php56_exitCode_idx" ON public.result_php56 USING brin ("exitCode");


--
-- Name: result_php56_input_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX result_php56_input_idx ON public.result_php56 USING btree (input);

ALTER TABLE public.result_php56 CLUSTER ON result_php56_input_idx;


--
-- Name: result_php5x_exitCode_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_php5x_exitCode_idx" ON public.result_php5x USING brin ("exitCode");


--
-- Name: result_php5x_input_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX result_php5x_input_idx ON public.result_php5x USING btree (input);

ALTER TABLE public.result_php5x CLUSTER ON result_php5x_input_idx;


--
-- Name: result_php70_exitCode_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_php70_exitCode_idx" ON public.result_php70 USING brin ("exitCode");


--
-- Name: result_php70_input_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX result_php70_input_idx ON public.result_php70 USING btree (input);

ALTER TABLE public.result_php70 CLUSTER ON result_php70_input_idx;


--
-- Name: result_php71_exitCode_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_php71_exitCode_idx" ON public.result_php71 USING brin ("exitCode");


--
-- Name: result_php71_input_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX result_php71_input_idx ON public.result_php71 USING btree (input);

ALTER TABLE public.result_php71 CLUSTER ON result_php71_input_idx;


--
-- Name: result_php72_exitCode_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_php72_exitCode_idx" ON public.result_php72 USING brin ("exitCode");


--
-- Name: result_php72_input_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX result_php72_input_idx ON public.result_php72 USING btree (input);

ALTER TABLE public.result_php72 CLUSTER ON result_php72_input_idx;


--
-- Name: result_php73_exitCode_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_php73_exitCode_idx" ON public.result_php73 USING brin ("exitCode");


--
-- Name: result_php73_input_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX result_php73_input_idx ON public.result_php73 USING btree (input);

ALTER TABLE public.result_php73 CLUSTER ON result_php73_input_idx;


--
-- Name: result_php74_exitCode_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_php74_exitCode_idx" ON public.result_php74 USING brin ("exitCode");


--
-- Name: result_php74_input_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX result_php74_input_idx ON public.result_php74 USING btree (input);

ALTER TABLE public.result_php74 CLUSTER ON result_php74_input_idx;


--
-- Name: result_php80_exitCode_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_php80_exitCode_idx" ON public.result_php80 USING brin ("exitCode");


--
-- Name: result_php80_input_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX result_php80_input_idx ON public.result_php80 USING btree (input);

ALTER TABLE public.result_php80 CLUSTER ON result_php80_input_idx;


--
-- Name: result_php81_exitCode_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_php81_exitCode_idx" ON public.result_php81 USING brin ("exitCode");


--
-- Name: result_php81_input_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX result_php81_input_idx ON public.result_php81 USING btree (input);

ALTER TABLE public.result_php81 CLUSTER ON result_php81_input_idx;


--
-- Name: result_php82_exitCode_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_php82_exitCode_idx" ON public.result_php82 USING brin ("exitCode");


--
-- Name: result_php82_input_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX result_php82_input_idx ON public.result_php82 USING btree (input);


--
-- Name: result_rfc_exitCode_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "result_rfc_exitCode_idx" ON public.result_rfc USING brin ("exitCode");


--
-- Name: result_rfc_input_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX result_rfc_input_idx ON public.result_rfc USING btree (input);

ALTER TABLE public.result_rfc CLUSTER ON result_rfc_input_idx;


--
-- Name: submitLast; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "submitLast" ON public.submit USING btree (input);

ALTER TABLE public.submit CLUSTER ON "submitLast";


--
-- Name: submitRecent; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX "submitRecent" ON public.submit USING btree (ip) WHERE (created > '2023-09-01 00:00:00'::timestamp without time zone);


--
-- Name: result_helper_exitCode_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultExitCode" ATTACH PARTITION public."result_helper_exitCode_idx";


--
-- Name: result_helper_input_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultInput" ATTACH PARTITION public.result_helper_input_idx;


--
-- Name: result_php4_exitCode_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultExitCode" ATTACH PARTITION public."result_php4_exitCode_idx";


--
-- Name: result_php4_input_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultInput" ATTACH PARTITION public.result_php4_input_idx;


--
-- Name: result_php53_exitCode_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultExitCode" ATTACH PARTITION public."result_php53_exitCode_idx";


--
-- Name: result_php53_input_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultInput" ATTACH PARTITION public.result_php53_input_idx;


--
-- Name: result_php54_exitCode_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultExitCode" ATTACH PARTITION public."result_php54_exitCode_idx";


--
-- Name: result_php54_input_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultInput" ATTACH PARTITION public.result_php54_input_idx;


--
-- Name: result_php55_exitCode_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultExitCode" ATTACH PARTITION public."result_php55_exitCode_idx";


--
-- Name: result_php55_input_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultInput" ATTACH PARTITION public.result_php55_input_idx;


--
-- Name: result_php56_exitCode_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultExitCode" ATTACH PARTITION public."result_php56_exitCode_idx";


--
-- Name: result_php56_input_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultInput" ATTACH PARTITION public.result_php56_input_idx;


--
-- Name: result_php5x_exitCode_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultExitCode" ATTACH PARTITION public."result_php5x_exitCode_idx";


--
-- Name: result_php5x_input_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultInput" ATTACH PARTITION public.result_php5x_input_idx;


--
-- Name: result_php70_exitCode_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultExitCode" ATTACH PARTITION public."result_php70_exitCode_idx";


--
-- Name: result_php70_input_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultInput" ATTACH PARTITION public.result_php70_input_idx;


--
-- Name: result_php71_exitCode_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultExitCode" ATTACH PARTITION public."result_php71_exitCode_idx";


--
-- Name: result_php71_input_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultInput" ATTACH PARTITION public.result_php71_input_idx;


--
-- Name: result_php72_exitCode_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultExitCode" ATTACH PARTITION public."result_php72_exitCode_idx";


--
-- Name: result_php72_input_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultInput" ATTACH PARTITION public.result_php72_input_idx;


--
-- Name: result_php73_exitCode_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultExitCode" ATTACH PARTITION public."result_php73_exitCode_idx";


--
-- Name: result_php73_input_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultInput" ATTACH PARTITION public.result_php73_input_idx;


--
-- Name: result_php74_exitCode_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultExitCode" ATTACH PARTITION public."result_php74_exitCode_idx";


--
-- Name: result_php74_input_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultInput" ATTACH PARTITION public.result_php74_input_idx;


--
-- Name: result_php80_exitCode_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultExitCode" ATTACH PARTITION public."result_php80_exitCode_idx";


--
-- Name: result_php80_input_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultInput" ATTACH PARTITION public.result_php80_input_idx;


--
-- Name: result_php81_exitCode_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultExitCode" ATTACH PARTITION public."result_php81_exitCode_idx";


--
-- Name: result_php81_input_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultInput" ATTACH PARTITION public.result_php81_input_idx;


--
-- Name: result_php82_exitCode_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultExitCode" ATTACH PARTITION public."result_php82_exitCode_idx";


--
-- Name: result_php82_input_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultInput" ATTACH PARTITION public.result_php82_input_idx;


--
-- Name: result_rfc_exitCode_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultExitCode" ATTACH PARTITION public."result_rfc_exitCode_idx";


--
-- Name: result_rfc_input_idx; Type: INDEX ATTACH; Schema: public; Owner: postgres
--

ALTER INDEX public."resultInput" ATTACH PARTITION public.result_rfc_input_idx;


--
-- Name: queue queue_insert_notify; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER queue_insert_notify AFTER INSERT ON public.queue FOR EACH ROW EXECUTE FUNCTION public.notify_daemon('queue');


--
-- Name: result result_mutated; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER result_mutated AFTER UPDATE ON public.result FOR EACH ROW WHEN (((old.mutations <> new.mutations) AND (new.version > 31))) EXECUTE FUNCTION public.input_mutated();


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
-- Name: result result_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE public.result
    ADD CONSTRAINT result_input_fkey FOREIGN KEY (input) REFERENCES public.input(id);


--
-- Name: result result_output_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE public.result
    ADD CONSTRAINT result_output_fkey FOREIGN KEY (output) REFERENCES public.output(id);


--
-- Name: result result_version_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE public.result
    ADD CONSTRAINT result_version_fkey FOREIGN KEY (version) REFERENCES public.version(id) ON DELETE CASCADE;


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
-- Name: TABLE result; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,DELETE ON TABLE public.result TO website;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.result TO daemon;


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
-- Name: TABLE result_bughunt; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT ON TABLE public.result_bughunt TO PUBLIC;


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
