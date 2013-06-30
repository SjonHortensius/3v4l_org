--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner:
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner:
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: input; Type: TABLE; Schema: public; Owner: phpshell; Tablespace:
--

CREATE TABLE input (
    short character varying(28) NOT NULL,
    source character varying(28),
    type character varying(10),
    hash character varying(28) NOT NULL,
    state character varying(4) NOT NULL
);


ALTER TABLE public.input OWNER TO phpshell;

--
-- Name: output; Type: TABLE; Schema: public; Owner: phpshell; Tablespace:
--

CREATE TABLE output (
    hash character varying(28) NOT NULL,
    raw bytea NOT NULL
);


ALTER TABLE public.output OWNER TO phpshell;

--
-- Name: result; Type: TABLE; Schema: public; Owner: phpshell; Tablespace:
--

CREATE TABLE result (
    input character varying(28) NOT NULL,
    output character varying(28) NOT NULL,
    version character varying(11) NOT NULL,
    "exitCode" integer NOT NULL,
    created timestamp without time zone NOT NULL,
    "userTime" numeric NOT NULL,
    "systemTime" numeric NOT NULL,
    "maxMemory" numeric NOT NULL
);


ALTER TABLE public.result OWNER TO phpshell;

--
-- Name: submit; Type: TABLE; Schema: public; Owner: phpshell; Tablespace:
--

CREATE TABLE submit (
    input character varying(28) NOT NULL,
    ip character varying(15) NOT NULL,
    created timestamp without time zone,
    updated timestamp without time zone,
    count integer
);


ALTER TABLE public.submit OWNER TO phpshell;

--
-- Name: version; Type: TABLE; Schema: public; Owner: phpshell; Tablespace:
--

CREATE TABLE version (
    name character varying(11) NOT NULL,
    released date,
    "order" integer
);


ALTER TABLE public.version OWNER TO phpshell;

--
-- Name: input_hash_key; Type: CONSTRAINT; Schema: public; Owner: phpshell; Tablespace:
--

ALTER TABLE ONLY input
    ADD CONSTRAINT input_hash_key UNIQUE (hash);


--
-- Name: input_pkey; Type: CONSTRAINT; Schema: public; Owner: phpshell; Tablespace:
--

ALTER TABLE ONLY input
    ADD CONSTRAINT input_pkey PRIMARY KEY (short);


--
-- Name: output_pkey; Type: CONSTRAINT; Schema: public; Owner: phpshell; Tablespace:
--

ALTER TABLE ONLY output
    ADD CONSTRAINT output_pkey PRIMARY KEY (hash);


--
-- Name: submit_pkey; Type: CONSTRAINT; Schema: public; Owner: phpshell; Tablespace:
--

ALTER TABLE ONLY submit
    ADD CONSTRAINT submit_pkey PRIMARY KEY (input, ip);


--
-- Name: version_pkey; Type: CONSTRAINT; Schema: public; Owner: phpshell; Tablespace:
--

ALTER TABLE ONLY version
    ADD CONSTRAINT version_pkey PRIMARY KEY (name);


--
-- Name: inputIp; Type: INDEX; Schema: public; Owner: phpshell; Tablespace:
--

CREATE UNIQUE INDEX "inputIp" ON submit USING btree (input, ip);


--
-- Name: versionInput; Type: INDEX; Schema: public; Owner: phpshell; Tablespace:
--

CREATE INDEX "versionInput" ON result USING btree (input, version);


--
-- Name: insert_ignore_duplicate; Type: RULE; Schema: public; Owner: phpshell
--

CREATE RULE insert_ignore_duplicate AS ON INSERT TO output WHERE (EXISTS (SELECT 1 FROM output WHERE ((output.hash)::text = (new.hash)::text))) DO INSTEAD NOTHING;


--
-- Name: input_source_fkey; Type: FK CONSTRAINT; Schema: public; Owner: phpshell
--

ALTER TABLE ONLY input
    ADD CONSTRAINT input_source_fkey FOREIGN KEY (source) REFERENCES input(short) ON UPDATE RESTRICT ON DELETE RESTRICT DEFERRABLE;


--
-- Name: result_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: phpshell
--

ALTER TABLE ONLY result
    ADD CONSTRAINT result_input_fkey FOREIGN KEY (input) REFERENCES input(short) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: result_output_fkey; Type: FK CONSTRAINT; Schema: public; Owner: phpshell
--

ALTER TABLE ONLY result
    ADD CONSTRAINT result_output_fkey FOREIGN KEY (output) REFERENCES output(hash) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: result_version_fkey; Type: FK CONSTRAINT; Schema: public; Owner: phpshell
--

ALTER TABLE ONLY result
    ADD CONSTRAINT result_version_fkey FOREIGN KEY (version) REFERENCES version(name) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: submit_input_fkey; Type: FK CONSTRAINT; Schema: public; Owner: phpshell
--

ALTER TABLE ONLY submit
    ADD CONSTRAINT submit_input_fkey FOREIGN KEY (input) REFERENCES input(short) ON UPDATE RESTRICT ON DELETE CASCADE;


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- Name: input; Type: ACL; Schema: public; Owner: phpshell
--

REVOKE ALL ON TABLE input FROM PUBLIC;
REVOKE ALL ON TABLE input FROM phpshell;
GRANT ALL ON TABLE input TO phpshell;
GRANT SELECT,INSERT ON TABLE input TO website;
GRANT SELECT ON TABLE input TO daemon;


--
-- Name: input.state; Type: ACL; Schema: public; Owner: phpshell
--

REVOKE ALL(state) ON TABLE input FROM PUBLIC;
REVOKE ALL(state) ON TABLE input FROM phpshell;
GRANT UPDATE(state) ON TABLE input TO daemon;


--
-- Name: output; Type: ACL; Schema: public; Owner: phpshell
--

REVOKE ALL ON TABLE output FROM PUBLIC;
REVOKE ALL ON TABLE output FROM phpshell;
GRANT ALL ON TABLE output TO phpshell;
GRANT INSERT ON TABLE output TO daemon;
GRANT SELECT ON TABLE output TO website;


--
-- Name: result; Type: ACL; Schema: public; Owner: phpshell
--

REVOKE ALL ON TABLE result FROM PUBLIC;
REVOKE ALL ON TABLE result FROM phpshell;
GRANT ALL ON TABLE result TO phpshell;
GRANT INSERT ON TABLE result TO daemon;
GRANT SELECT,UPDATE ON TABLE result TO website;


--
-- Name: submit; Type: ACL; Schema: public; Owner: phpshell
--

REVOKE ALL ON TABLE submit FROM PUBLIC;
REVOKE ALL ON TABLE submit FROM phpshell;
GRANT ALL ON TABLE submit TO phpshell;
GRANT INSERT,UPDATE ON TABLE submit TO website;


--
-- Name: version; Type: ACL; Schema: public; Owner: phpshell
--

REVOKE ALL ON TABLE version FROM PUBLIC;
REVOKE ALL ON TABLE version FROM phpshell;
GRANT ALL ON TABLE version TO phpshell;
GRANT SELECT ON TABLE version TO website;


--
-- PostgreSQL database dump complete
--