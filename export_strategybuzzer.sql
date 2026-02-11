--
-- PostgreSQL database dump
--

\restrict ZXx4ypxjLqoHjJiJ3Oc7Xbj6IIwQBEXNVNZRgipDXcNRPvC9wARwdPAE6WHDPPt

-- Dumped from database version 16.11 (df20cf9)
-- Dumped by pg_dump version 16.10

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

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: coin_ledger; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.coin_ledger (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    delta integer NOT NULL,
    reason character varying(255) NOT NULL,
    ref_type character varying(255),
    ref_id bigint,
    balance_after integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: coin_ledger_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.coin_ledger_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: coin_ledger_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.coin_ledger_id_seq OWNED BY public.coin_ledger.id;


--
-- Name: daily_quest_rotation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.daily_quest_rotation (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    rotation_date date,
    quest_ids json
);


--
-- Name: daily_quest_rotation_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.daily_quest_rotation_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: daily_quest_rotation_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.daily_quest_rotation_id_seq OWNED BY public.daily_quest_rotation.id;


--
-- Name: duo_matches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.duo_matches (
    id bigint NOT NULL,
    player1_id bigint NOT NULL,
    player2_id bigint NOT NULL,
    status character varying(255) DEFAULT 'waiting'::character varying NOT NULL,
    match_type character varying(255) DEFAULT 'random'::character varying NOT NULL,
    player1_score integer DEFAULT 0 NOT NULL,
    player2_score integer DEFAULT 0 NOT NULL,
    winner_id bigint,
    theme character varying(255),
    player1_level integer DEFAULT 1 NOT NULL,
    player2_level integer DEFAULT 1 NOT NULL,
    player1_points_earned integer DEFAULT 0 NOT NULL,
    player2_points_earned integer DEFAULT 0 NOT NULL,
    game_state json,
    started_at timestamp(0) without time zone,
    finished_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    lobby_code character varying(10),
    player1_coins_earned integer DEFAULT 0 NOT NULL,
    player2_coins_earned integer DEFAULT 0 NOT NULL,
    room_id character varying(255)
);


--
-- Name: duo_matches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.duo_matches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: duo_matches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.duo_matches_id_seq OWNED BY public.duo_matches.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: invitations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.invitations (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: invitations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.invitations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: invitations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.invitations_id_seq OWNED BY public.invitations.id;


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: league_individual_matches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.league_individual_matches (
    id bigint NOT NULL,
    player1_id bigint NOT NULL,
    player2_id bigint NOT NULL,
    winner_id bigint,
    status character varying(255) DEFAULT 'waiting'::character varying NOT NULL,
    player1_level integer DEFAULT 1 NOT NULL,
    player2_level integer DEFAULT 1 NOT NULL,
    game_state json,
    player1_points_earned integer DEFAULT 0 NOT NULL,
    player2_points_earned integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    player1_coins_earned integer DEFAULT 0 NOT NULL,
    player2_coins_earned integer DEFAULT 0 NOT NULL
);


--
-- Name: league_individual_matches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.league_individual_matches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: league_individual_matches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.league_individual_matches_id_seq OWNED BY public.league_individual_matches.id;


--
-- Name: league_individual_stats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.league_individual_stats (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    level integer DEFAULT 1 NOT NULL,
    matches_played integer DEFAULT 0 NOT NULL,
    matches_won integer DEFAULT 0 NOT NULL,
    matches_lost integer DEFAULT 0 NOT NULL,
    total_points integer DEFAULT 0 NOT NULL,
    initialized boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: league_individual_stats_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.league_individual_stats_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: league_individual_stats_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.league_individual_stats_id_seq OWNED BY public.league_individual_stats.id;


--
-- Name: league_team_match_participants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.league_team_match_participants (
    id bigint NOT NULL,
    match_id bigint NOT NULL,
    team_id bigint NOT NULL,
    user_id bigint NOT NULL,
    score integer DEFAULT 0,
    correct_answers integer DEFAULT 0,
    wrong_answers integer DEFAULT 0,
    buzzes integer DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: league_team_match_participants_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.league_team_match_participants_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: league_team_match_participants_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.league_team_match_participants_id_seq OWNED BY public.league_team_match_participants.id;


--
-- Name: league_team_matches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.league_team_matches (
    id bigint NOT NULL,
    team1_id bigint NOT NULL,
    team2_id bigint NOT NULL,
    team1_level integer NOT NULL,
    team2_level integer NOT NULL,
    winner_team_id bigint,
    status character varying(255) DEFAULT 'waiting'::character varying NOT NULL,
    game_state json,
    team1_points_earned integer DEFAULT 0 NOT NULL,
    team2_points_earned integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_mode character varying(255) DEFAULT 'classique'::character varying NOT NULL,
    player_order json,
    duel_pairings json,
    relay_indices json,
    completed_at timestamp without time zone,
    match_division character varying(255) DEFAULT 'bronze'::character varying NOT NULL
);


--
-- Name: league_team_matches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.league_team_matches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: league_team_matches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.league_team_matches_id_seq OWNED BY public.league_team_matches.id;


--
-- Name: lobby_game_starts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.lobby_game_starts (
    id uuid NOT NULL,
    lobby_code character varying(10) NOT NULL,
    bet_amount integer DEFAULT 0 NOT NULL,
    refunded_at timestamp(0) without time zone,
    refund_reason character varying(255),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: master_game_codes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.master_game_codes (
    id bigint NOT NULL,
    master_game_id bigint NOT NULL,
    code character varying(10) NOT NULL,
    role character varying(255) DEFAULT 'player'::character varying NOT NULL,
    side character varying(255),
    capacity integer DEFAULT 1 NOT NULL,
    used integer DEFAULT 0 NOT NULL,
    state character varying(255) DEFAULT 'active'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT master_game_codes_role_check CHECK (((role)::text = ANY ((ARRAY['player'::character varying, 'host'::character varying])::text[]))),
    CONSTRAINT master_game_codes_state_check CHECK (((state)::text = ANY ((ARRAY['active'::character varying, 'revoked'::character varying])::text[])))
);


--
-- Name: master_game_codes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.master_game_codes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: master_game_codes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.master_game_codes_id_seq OWNED BY public.master_game_codes.id;


--
-- Name: master_game_players; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.master_game_players (
    id bigint NOT NULL,
    master_game_id bigint NOT NULL,
    user_id bigint NOT NULL,
    master_game_code_id bigint,
    side character varying(255),
    score integer DEFAULT 0 NOT NULL,
    answered json DEFAULT '{}'::json NOT NULL,
    status character varying(255) DEFAULT 'waiting'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    team_id bigint,
    seat_index integer,
    is_captain boolean DEFAULT false NOT NULL,
    CONSTRAINT master_game_players_status_check CHECK (((status)::text = ANY ((ARRAY['waiting'::character varying, 'playing'::character varying, 'disconnected'::character varying])::text[])))
);


--
-- Name: master_game_players_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.master_game_players_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: master_game_players_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.master_game_players_id_seq OWNED BY public.master_game_players.id;


--
-- Name: master_game_questions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.master_game_questions (
    id bigint NOT NULL,
    master_game_id bigint NOT NULL,
    question_number integer NOT NULL,
    type character varying(255) DEFAULT 'multiple_choice'::character varying NOT NULL,
    text text NOT NULL,
    choices json,
    correct_indexes json NOT NULL,
    media_url character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_tiebreaker boolean DEFAULT false NOT NULL,
    CONSTRAINT master_game_questions_type_check CHECK (((type)::text = ANY ((ARRAY['true_false'::character varying, 'multiple_choice'::character varying, 'image'::character varying])::text[])))
);


--
-- Name: master_game_questions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.master_game_questions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: master_game_questions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.master_game_questions_id_seq OWNED BY public.master_game_questions.id;


--
-- Name: master_game_teams; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.master_game_teams (
    id bigint NOT NULL,
    master_game_id bigint NOT NULL,
    name character varying(100) NOT NULL,
    color character varying(20),
    team_order integer DEFAULT 0 NOT NULL,
    max_players integer DEFAULT 10 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: master_game_teams_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.master_game_teams_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: master_game_teams_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.master_game_teams_id_seq OWNED BY public.master_game_teams.id;


--
-- Name: master_games; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.master_games (
    id bigint NOT NULL,
    game_code character varying(10) NOT NULL,
    firebase_id character varying(255),
    host_user_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    languages json DEFAULT '["FR"]'::json NOT NULL,
    participants_expected integer DEFAULT 10 NOT NULL,
    mode character varying(255) DEFAULT 'podium'::character varying NOT NULL,
    total_questions integer DEFAULT 20 NOT NULL,
    question_types json DEFAULT '["multiple_choice"]'::json NOT NULL,
    domain_type character varying(255) DEFAULT 'theme'::character varying NOT NULL,
    theme character varying(255),
    school_country character varying(255),
    school_level character varying(255),
    school_subject character varying(255),
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    current_question integer DEFAULT 0 NOT NULL,
    quiz_validated boolean DEFAULT false NOT NULL,
    started_at timestamp(0) without time zone,
    ended_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    creation_mode character varying(255) DEFAULT 'automatique'::character varying NOT NULL,
    school_grade character varying(50),
    ai_images_count integer DEFAULT 0 NOT NULL,
    structure_type character varying(50) DEFAULT 'free_for_all'::character varying NOT NULL,
    team_count integer,
    team_size_cap integer DEFAULT 20 NOT NULL,
    skill_policy character varying(50) DEFAULT 'all_players'::character varying NOT NULL,
    buzz_rule character varying(50) DEFAULT 'first_buzz_locks'::character varying NOT NULL,
    CONSTRAINT master_games_mode_check CHECK (((mode)::text = ANY ((ARRAY['face_to_face'::character varying, 'one_vs_all'::character varying, 'podium'::character varying, 'groups'::character varying])::text[]))),
    CONSTRAINT master_games_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'lobby'::character varying, 'running'::character varying, 'ended'::character varying])::text[])))
);


--
-- Name: master_games_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.master_games_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: master_games_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.master_games_id_seq OWNED BY public.master_games.id;


--
-- Name: match_performances; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.match_performances (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    game_mode character varying(255) NOT NULL,
    game_id character varying(255) NOT NULL,
    performance numeric(5,2) NOT NULL,
    rounds_played integer,
    is_victory boolean NOT NULL,
    played_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: match_performances_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.match_performances_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: match_performances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.match_performances_id_seq OWNED BY public.match_performances.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: payments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payments (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    stripe_session_id character varying(255),
    stripe_payment_intent_id character varying(255),
    product_key character varying(255) NOT NULL,
    amount_cents integer NOT NULL,
    currency character varying(3) DEFAULT 'usd'::character varying NOT NULL,
    coins_awarded integer DEFAULT 0 NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    metadata json,
    processed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: payments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payments_id_seq OWNED BY public.payments.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: player_contacts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.player_contacts (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    contact_user_id bigint NOT NULL,
    matches_played_together integer DEFAULT 0 NOT NULL,
    matches_won integer DEFAULT 0 NOT NULL,
    matches_lost integer DEFAULT 0 NOT NULL,
    decisive_rounds_played integer DEFAULT 0 NOT NULL,
    decisive_rounds_won integer DEFAULT 0 NOT NULL,
    last_played_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: player_contacts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.player_contacts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: player_contacts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.player_contacts_id_seq OWNED BY public.player_contacts.id;


--
-- Name: player_divisions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.player_divisions (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    mode character varying(255) NOT NULL,
    division character varying(255) DEFAULT 'bronze'::character varying NOT NULL,
    points integer DEFAULT 0 NOT NULL,
    level integer DEFAULT 1 NOT NULL,
    rank integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    initial_efficiency numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    matches_won integer DEFAULT 0 NOT NULL,
    matches_lost integer DEFAULT 0 NOT NULL
);


--
-- Name: player_divisions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.player_divisions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: player_divisions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.player_divisions_id_seq OWNED BY public.player_divisions.id;


--
-- Name: player_duo_stats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.player_duo_stats (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    total_matches integer DEFAULT 0 NOT NULL,
    victories integer DEFAULT 0 NOT NULL,
    defeats integer DEFAULT 0 NOT NULL,
    level integer DEFAULT 0 NOT NULL,
    win_rate numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    current_streak integer DEFAULT 0 NOT NULL,
    best_win_streak integer DEFAULT 0 NOT NULL,
    best_lose_streak integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: player_duo_stats_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.player_duo_stats_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: player_duo_stats_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.player_duo_stats_id_seq OWNED BY public.player_duo_stats.id;


--
-- Name: player_group_members; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.player_group_members (
    id integer NOT NULL,
    group_id integer NOT NULL,
    member_user_id integer NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: player_group_members_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.player_group_members_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: player_group_members_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.player_group_members_id_seq OWNED BY public.player_group_members.id;


--
-- Name: player_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.player_groups (
    id integer NOT NULL,
    user_id integer NOT NULL,
    name character varying(100) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: player_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.player_groups_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: player_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.player_groups_id_seq OWNED BY public.player_groups.id;


--
-- Name: player_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.player_messages (
    id bigint NOT NULL,
    sender_id bigint NOT NULL,
    receiver_id bigint NOT NULL,
    message text NOT NULL,
    is_read boolean DEFAULT false NOT NULL,
    related_match_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: player_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.player_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: player_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.player_messages_id_seq OWNED BY public.player_messages.id;


--
-- Name: player_statistics; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.player_statistics (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    game_mode character varying(255) NOT NULL,
    scope character varying(255) NOT NULL,
    game_id character varying(255),
    round_number integer,
    total_questions integer DEFAULT 0 NOT NULL,
    questions_buzzed integer DEFAULT 0 NOT NULL,
    correct_answers integer DEFAULT 0 NOT NULL,
    wrong_answers integer DEFAULT 0 NOT NULL,
    points_earned integer DEFAULT 0 NOT NULL,
    points_possible integer DEFAULT 0 NOT NULL,
    efficacite_joueur numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    taux_participation numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    taux_precision numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    ratio_performance numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    details json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    efficacite_partie numeric(5,2),
    efficacite_manche numeric(5,2)
);


--
-- Name: player_statistics_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.player_statistics_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: player_statistics_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.player_statistics_id_seq OWNED BY public.player_statistics.id;


--
-- Name: profile_stats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.profile_stats (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    solo_matchs_joues integer DEFAULT 0 NOT NULL,
    solo_victoires integer DEFAULT 0 NOT NULL,
    solo_defaites integer DEFAULT 0 NOT NULL,
    solo_ratio_victoire numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    solo_matchs_3_manches integer DEFAULT 0 NOT NULL,
    solo_victoires_3_manches integer DEFAULT 0 NOT NULL,
    solo_performance_moyenne numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    duo_matchs_joues integer DEFAULT 0 NOT NULL,
    duo_victoires integer DEFAULT 0 NOT NULL,
    duo_defaites integer DEFAULT 0 NOT NULL,
    duo_ratio_victoire numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    duo_performance_moyenne numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    league_matchs_joues integer DEFAULT 0 NOT NULL,
    league_victoires integer DEFAULT 0 NOT NULL,
    league_defaites integer DEFAULT 0 NOT NULL,
    league_ratio_victoire numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    league_performance_moyenne numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    solo_efficacite_joueur numeric(5,2) DEFAULT 0,
    duo_efficacite_joueur numeric(5,2) DEFAULT 0,
    league_efficacite_joueur numeric(5,2) DEFAULT 0
);


--
-- Name: profile_stats_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.profile_stats_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: profile_stats_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.profile_stats_id_seq OWNED BY public.profile_stats.id;


--
-- Name: purchases; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchases (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    item_name character varying(255) NOT NULL,
    item_type character varying(255) NOT NULL,
    price numeric(8,2),
    currency character varying(255) DEFAULT 'USD'::character varying NOT NULL,
    payment_method character varying(255),
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    transaction_id character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: purchases_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchases_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchases_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchases_id_seq OWNED BY public.purchases.id;


--
-- Name: question_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.question_history (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    question_id character varying(255) NOT NULL,
    question_hash character varying(32) NOT NULL,
    correct_answer character varying(500) NOT NULL,
    theme character varying(100),
    niveau integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: question_history_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.question_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: question_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.question_history_id_seq OWNED BY public.question_history.id;


--
-- Name: quests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.quests (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    category character varying(255) NOT NULL,
    condition text NOT NULL,
    reward_coins integer DEFAULT 0 NOT NULL,
    rarity character varying(255) DEFAULT 'Standard'::character varying NOT NULL,
    badge_emoji character varying(255) NOT NULL,
    badge_description character varying(255) NOT NULL,
    detection_code character varying(255) NOT NULL,
    detection_params json,
    auto_complete boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: quests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.quests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: quests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.quests_id_seq OWNED BY public.quests.id;


--
-- Name: team_invitations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.team_invitations (
    id bigint NOT NULL,
    team_id bigint NOT NULL,
    user_id bigint NOT NULL,
    invited_by bigint NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: team_invitations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.team_invitations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: team_invitations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.team_invitations_id_seq OWNED BY public.team_invitations.id;


--
-- Name: team_join_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.team_join_requests (
    id integer NOT NULL,
    team_id bigint NOT NULL,
    user_id bigint NOT NULL,
    message text,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


--
-- Name: team_join_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.team_join_requests_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: team_join_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.team_join_requests_id_seq OWNED BY public.team_join_requests.id;


--
-- Name: team_members; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.team_members (
    id bigint NOT NULL,
    team_id bigint NOT NULL,
    user_id bigint NOT NULL,
    role character varying(255) DEFAULT 'member'::character varying NOT NULL,
    joined_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: team_members_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.team_members_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: team_members_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.team_members_id_seq OWNED BY public.team_members.id;


--
-- Name: teams; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.teams (
    id bigint NOT NULL,
    name character varying(10) NOT NULL,
    tag character varying(10) NOT NULL,
    captain_id bigint NOT NULL,
    division character varying(255) DEFAULT 'bronze'::character varying NOT NULL,
    points integer DEFAULT 0 NOT NULL,
    level integer DEFAULT 1 NOT NULL,
    matches_played integer DEFAULT 0 NOT NULL,
    matches_won integer DEFAULT 0 NOT NULL,
    matches_lost integer DEFAULT 0 NOT NULL,
    is_recruiting boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    emblem_category character varying(50) DEFAULT 'animals'::character varying,
    emblem_index integer DEFAULT 1,
    custom_emblem_path character varying(500),
    team_code character varying(10)
);


--
-- Name: teams_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.teams_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: teams_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.teams_id_seq OWNED BY public.teams.id;


--
-- Name: user_avatars; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_avatars (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    avatar_name character varying(255) NOT NULL,
    unlocked boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: user_avatars_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_avatars_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_avatars_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_avatars_id_seq OWNED BY public.user_avatars.id;


--
-- Name: user_preferences; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_preferences (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    avatar_joueur character varying(255),
    avatar_strategique character varying(255),
    strategie_preferee character varying(255),
    langue character varying(255) DEFAULT 'fr'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: user_preferences_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_preferences_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_preferences_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_preferences_id_seq OWNED BY public.user_preferences.id;


--
-- Name: user_quest_progress; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_quest_progress (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    quest_id bigint NOT NULL,
    completed_at timestamp(0) without time zone,
    progress json,
    rewarded boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: user_quest_progress_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_quest_progress_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_quest_progress_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_quest_progress_id_seq OWNED BY public.user_quest_progress.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255),
    coins integer DEFAULT 25 NOT NULL,
    lives integer DEFAULT 3 NOT NULL,
    infinite_lives_until timestamp(0) without time zone,
    rank character varying(255) DEFAULT 'Rookie'::character varying NOT NULL,
    profile_settings json,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    intelligence_pieces integer DEFAULT 0 NOT NULL,
    profile_completed boolean DEFAULT false NOT NULL,
    master_purchased boolean DEFAULT false NOT NULL,
    player_code character varying(10),
    next_life_regen timestamp(0) without time zone,
    ambient_music_id character varying(255) DEFAULT 'strategybuzzer'::character varying NOT NULL,
    ambient_music_enabled boolean DEFAULT true NOT NULL,
    gameplay_music_id character varying(255),
    gameplay_music_enabled boolean DEFAULT true NOT NULL,
    preferred_language character varying(5) DEFAULT 'fr'::character varying NOT NULL,
    duo_purchased boolean DEFAULT false NOT NULL,
    league_purchased boolean DEFAULT false NOT NULL,
    temp_access_division character varying(255),
    temp_access_expires_at timestamp(0) without time zone,
    current_match_id character varying(255),
    match_started_at timestamp(0) without time zone,
    competence_coins integer DEFAULT 250 NOT NULL
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: coin_ledger id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coin_ledger ALTER COLUMN id SET DEFAULT nextval('public.coin_ledger_id_seq'::regclass);


--
-- Name: daily_quest_rotation id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_quest_rotation ALTER COLUMN id SET DEFAULT nextval('public.daily_quest_rotation_id_seq'::regclass);


--
-- Name: duo_matches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.duo_matches ALTER COLUMN id SET DEFAULT nextval('public.duo_matches_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: invitations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations ALTER COLUMN id SET DEFAULT nextval('public.invitations_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: league_individual_matches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_individual_matches ALTER COLUMN id SET DEFAULT nextval('public.league_individual_matches_id_seq'::regclass);


--
-- Name: league_individual_stats id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_individual_stats ALTER COLUMN id SET DEFAULT nextval('public.league_individual_stats_id_seq'::regclass);


--
-- Name: league_team_match_participants id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_team_match_participants ALTER COLUMN id SET DEFAULT nextval('public.league_team_match_participants_id_seq'::regclass);


--
-- Name: league_team_matches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_team_matches ALTER COLUMN id SET DEFAULT nextval('public.league_team_matches_id_seq'::regclass);


--
-- Name: master_game_codes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_codes ALTER COLUMN id SET DEFAULT nextval('public.master_game_codes_id_seq'::regclass);


--
-- Name: master_game_players id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_players ALTER COLUMN id SET DEFAULT nextval('public.master_game_players_id_seq'::regclass);


--
-- Name: master_game_questions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_questions ALTER COLUMN id SET DEFAULT nextval('public.master_game_questions_id_seq'::regclass);


--
-- Name: master_game_teams id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_teams ALTER COLUMN id SET DEFAULT nextval('public.master_game_teams_id_seq'::regclass);


--
-- Name: master_games id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_games ALTER COLUMN id SET DEFAULT nextval('public.master_games_id_seq'::regclass);


--
-- Name: match_performances id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.match_performances ALTER COLUMN id SET DEFAULT nextval('public.match_performances_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: payments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments ALTER COLUMN id SET DEFAULT nextval('public.payments_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: player_contacts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_contacts ALTER COLUMN id SET DEFAULT nextval('public.player_contacts_id_seq'::regclass);


--
-- Name: player_divisions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_divisions ALTER COLUMN id SET DEFAULT nextval('public.player_divisions_id_seq'::regclass);


--
-- Name: player_duo_stats id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_duo_stats ALTER COLUMN id SET DEFAULT nextval('public.player_duo_stats_id_seq'::regclass);


--
-- Name: player_group_members id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_group_members ALTER COLUMN id SET DEFAULT nextval('public.player_group_members_id_seq'::regclass);


--
-- Name: player_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_groups ALTER COLUMN id SET DEFAULT nextval('public.player_groups_id_seq'::regclass);


--
-- Name: player_messages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_messages ALTER COLUMN id SET DEFAULT nextval('public.player_messages_id_seq'::regclass);


--
-- Name: player_statistics id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_statistics ALTER COLUMN id SET DEFAULT nextval('public.player_statistics_id_seq'::regclass);


--
-- Name: profile_stats id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.profile_stats ALTER COLUMN id SET DEFAULT nextval('public.profile_stats_id_seq'::regclass);


--
-- Name: purchases id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchases ALTER COLUMN id SET DEFAULT nextval('public.purchases_id_seq'::regclass);


--
-- Name: question_history id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.question_history ALTER COLUMN id SET DEFAULT nextval('public.question_history_id_seq'::regclass);


--
-- Name: quests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quests ALTER COLUMN id SET DEFAULT nextval('public.quests_id_seq'::regclass);


--
-- Name: team_invitations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_invitations ALTER COLUMN id SET DEFAULT nextval('public.team_invitations_id_seq'::regclass);


--
-- Name: team_join_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_join_requests ALTER COLUMN id SET DEFAULT nextval('public.team_join_requests_id_seq'::regclass);


--
-- Name: team_members id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_members ALTER COLUMN id SET DEFAULT nextval('public.team_members_id_seq'::regclass);


--
-- Name: teams id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams ALTER COLUMN id SET DEFAULT nextval('public.teams_id_seq'::regclass);


--
-- Name: user_avatars id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_avatars ALTER COLUMN id SET DEFAULT nextval('public.user_avatars_id_seq'::regclass);


--
-- Name: user_preferences id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_preferences ALTER COLUMN id SET DEFAULT nextval('public.user_preferences_id_seq'::regclass);


--
-- Name: user_quest_progress id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_quest_progress ALTER COLUMN id SET DEFAULT nextval('public.user_quest_progress_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: coin_ledger; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.coin_ledger (id, user_id, delta, reason, ref_type, ref_id, balance_after, created_at, updated_at) FROM stdin;
1	3	-500	stratégique_purchase	\N	\N	500	2025-10-07 18:55:37	2025-10-07 18:55:37
2	3	-300	pack_purchase	\N	\N	200	2025-10-07 19:00:02	2025-10-07 19:00:02
3	4	-300	pack_purchase	\N	\N	700	2025-10-14 01:00:33	2025-10-14 01:00:33
4	7	-300	pack_purchase	\N	\N	700	2025-10-16 00:06:29	2025-10-16 00:06:29
5	7	-300	pack_purchase	\N	\N	400	2025-10-16 00:07:11	2025-10-16 00:07:11
6	8	-300	pack_purchase	\N	\N	700	2025-10-24 18:10:19	2025-10-24 18:10:19
7	3	-500	stratégique_purchase	\N	\N	1565	2025-10-30 13:38:10	2025-10-30 13:38:10
8	3	-1000	stratégique_purchase	\N	\N	565	2025-10-30 13:38:22	2025-10-30 13:38:22
9	6	-1000	stratégique_purchase	\N	\N	20	2025-12-02 16:18:04	2025-12-02 16:18:04
10	3	59	Victoire Solo niveau 49	solo_victory	49	634	2025-12-11 04:31:11	2025-12-11 04:31:11
11	3	-180	buzzer_purchase	\N	\N	454	2025-12-14 23:50:44	2025-12-14 23:50:44
12	10	11	Victoire Solo niveau 1	solo_victory	1	1021	2025-12-19 22:24:38	2025-12-19 22:24:38
13	4	-500	stratégique_purchase	\N	\N	200	2025-12-27 22:50:10	2025-12-27 22:50:10
14	4	11	Victoire Solo niveau 1	solo_victory	1	221	2026-01-03 21:48:40	2026-01-03 21:48:40
15	3	20	Victoire Solo niveau 12	solo_victory	12	474	2026-01-18 13:15:06	2026-01-18 13:15:06
16	3	13	Victoire Solo niveau 7 (+25% Stratège)	solo_victory	7	487	2026-01-26 11:55:11	2026-01-26 11:55:11
17	3	13	Victoire Solo niveau 7 (+25% Stratège)	solo_victory	7	500	2026-01-26 14:51:43	2026-01-26 14:51:43
\.


--
-- Data for Name: daily_quest_rotation; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.daily_quest_rotation (id, created_at, updated_at, rotation_date, quest_ids) FROM stdin;
\.


--
-- Data for Name: duo_matches; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.duo_matches (id, player1_id, player2_id, status, match_type, player1_score, player2_score, winner_id, theme, player1_level, player2_level, player1_points_earned, player2_points_earned, game_state, started_at, finished_at, created_at, updated_at, lobby_code, player1_coins_earned, player2_coins_earned, room_id) FROM stdin;
1	3	1	completed	random	9	6	3	Géographie	1	1	0	0	\N	\N	\N	2025-10-18 05:11:20	2025-10-09 05:11:20	\N	0	0	\N
2	3	1	completed	random	9	7	3	Culture	1	1	0	0	\N	\N	\N	2025-10-18 05:11:20	2025-09-28 05:11:20	\N	0	0	\N
3	3	1	completed	random	9	6	3	Science	1	1	0	0	\N	\N	\N	2025-10-05 05:11:20	2025-09-27 05:11:20	\N	0	0	\N
4	3	1	completed	random	10	8	3	Géographie	1	1	0	0	\N	\N	\N	2025-10-16 05:11:21	2025-10-13 05:11:21	\N	0	0	\N
5	3	1	completed	random	10	8	3	Sport	1	1	0	0	\N	\N	\N	2025-10-02 05:11:21	2025-10-12 05:11:21	\N	0	0	\N
6	3	1	completed	random	9	8	3	Culture	1	1	0	0	\N	\N	\N	2025-10-14 05:11:21	2025-10-01 05:11:21	\N	0	0	\N
7	3	1	completed	random	10	7	3	Culture	1	1	0	0	\N	\N	\N	2025-10-25 05:11:21	2025-10-21 05:11:21	\N	0	0	\N
8	3	1	completed	random	8	5	3	Géographie	1	1	0	0	\N	\N	\N	2025-10-18 05:11:21	2025-10-01 05:11:21	\N	0	0	\N
9	3	1	completed	random	9	5	3	Culture	1	1	0	0	\N	\N	\N	2025-10-05 05:11:21	2025-10-03 05:11:21	\N	0	0	\N
10	3	1	completed	random	10	9	3	Culture	1	1	0	0	\N	\N	\N	2025-10-09 05:11:21	2025-10-11 05:11:21	\N	0	0	\N
11	3	1	completed	random	9	7	3	Sport	1	1	0	0	\N	\N	\N	2025-10-04 05:11:21	2025-09-27 05:11:21	\N	0	0	\N
12	3	1	completed	random	10	8	3	Histoire	1	1	0	0	\N	\N	\N	2025-09-27 05:11:21	2025-10-24 05:11:21	\N	0	0	\N
13	3	1	completed	random	10	5	3	Histoire	1	1	0	0	\N	\N	\N	2025-09-30 05:11:21	2025-10-17 05:11:21	\N	0	0	\N
14	3	1	completed	random	8	5	3	Culture	1	1	0	0	\N	\N	\N	2025-09-28 05:11:21	2025-10-24 05:11:21	\N	0	0	\N
15	3	1	completed	random	9	7	3	Géographie	1	1	0	0	\N	\N	\N	2025-09-29 05:11:21	2025-10-19 05:11:21	\N	0	0	\N
16	3	1	completed	random	8	5	3	Histoire	1	1	0	0	\N	\N	\N	2025-10-02 05:11:21	2025-10-04 05:11:21	\N	0	0	\N
17	3	1	completed	random	8	7	3	Culture	1	1	0	0	\N	\N	\N	2025-10-21 05:11:21	2025-10-17 05:11:21	\N	0	0	\N
18	3	1	completed	random	9	7	3	Science	1	1	0	0	\N	\N	\N	2025-10-03 05:11:21	2025-09-27 05:11:21	\N	0	0	\N
19	3	1	completed	random	10	8	3	Science	1	1	0	0	\N	\N	\N	2025-09-26 05:11:21	2025-10-21 05:11:21	\N	0	0	\N
20	3	1	completed	random	9	5	3	Science	1	1	0	0	\N	\N	\N	2025-10-07 05:11:21	2025-09-29 05:11:21	\N	0	0	\N
21	3	1	completed	random	10	9	3	Sport	1	1	0	0	\N	\N	\N	2025-10-22 05:11:22	2025-10-10 05:11:22	\N	0	0	\N
22	3	1	completed	random	8	7	3	Géographie	1	1	0	0	\N	\N	\N	2025-10-07 05:11:22	2025-10-22 05:11:22	\N	0	0	\N
23	3	1	completed	random	10	5	3	Sport	1	1	0	0	\N	\N	\N	2025-09-27 05:11:22	2025-10-13 05:11:22	\N	0	0	\N
24	3	1	completed	random	10	5	3	Histoire	1	1	0	0	\N	\N	\N	2025-10-17 05:11:22	2025-10-03 05:11:22	\N	0	0	\N
25	3	1	completed	random	9	9	3	Culture	1	1	0	0	\N	\N	\N	2025-10-17 05:11:22	2025-10-11 05:11:22	\N	0	0	\N
26	3	1	completed	random	9	9	3	Sport	1	1	0	0	\N	\N	\N	2025-10-23 05:11:22	2025-10-07 05:11:22	\N	0	0	\N
27	3	1	completed	random	10	6	3	Histoire	1	1	0	0	\N	\N	\N	2025-09-26 05:11:22	2025-10-10 05:11:22	\N	0	0	\N
28	3	1	completed	random	8	8	3	Culture	1	1	0	0	\N	\N	\N	2025-10-04 05:11:22	2025-10-18 05:11:22	\N	0	0	\N
29	3	1	completed	random	9	6	3	Science	1	1	0	0	\N	\N	\N	2025-10-13 05:11:22	2025-10-09 05:11:22	\N	0	0	\N
30	3	1	completed	random	8	5	3	Science	1	1	0	0	\N	\N	\N	2025-10-20 05:11:22	2025-10-12 05:11:22	\N	0	0	\N
31	3	1	completed	random	8	7	3	Culture	1	1	0	0	\N	\N	\N	2025-10-23 05:11:22	2025-10-23 05:11:22	\N	0	0	\N
32	3	1	completed	random	8	6	3	Histoire	1	1	0	0	\N	\N	\N	2025-09-28 05:11:22	2025-10-09 05:11:22	\N	0	0	\N
33	3	1	completed	random	8	9	3	Science	1	1	0	0	\N	\N	\N	2025-10-12 05:11:22	2025-10-21 05:11:22	\N	0	0	\N
34	3	1	completed	random	10	5	3	Géographie	1	1	0	0	\N	\N	\N	2025-10-15 05:11:22	2025-10-16 05:11:22	\N	0	0	\N
35	3	1	completed	random	9	7	3	Sport	1	1	0	0	\N	\N	\N	2025-10-25 05:11:22	2025-10-16 05:11:22	\N	0	0	\N
36	3	1	completed	random	8	7	3	Culture	1	1	0	0	\N	\N	\N	2025-10-10 05:11:22	2025-10-24 05:11:22	\N	0	0	\N
37	3	1	completed	random	10	6	3	Histoire	1	1	0	0	\N	\N	\N	2025-10-22 05:11:23	2025-10-11 05:11:23	\N	0	0	\N
38	3	1	completed	random	9	8	3	Sport	1	1	0	0	\N	\N	\N	2025-10-09 05:11:23	2025-09-29 05:11:23	\N	0	0	\N
39	3	1	completed	random	10	7	3	Géographie	1	1	0	0	\N	\N	\N	2025-10-05 05:11:23	2025-10-12 05:11:23	\N	0	0	\N
40	3	1	completed	random	10	9	3	Géographie	1	1	0	0	\N	\N	\N	2025-10-01 05:11:23	2025-09-28 05:11:23	\N	0	0	\N
41	3	1	completed	random	9	9	3	Sport	1	1	0	0	\N	\N	\N	2025-10-07 05:11:23	2025-10-09 05:11:23	\N	0	0	\N
42	3	1	completed	random	9	5	3	Sport	1	1	0	0	\N	\N	\N	2025-09-27 05:11:23	2025-10-17 05:11:23	\N	0	0	\N
43	3	1	completed	random	10	5	3	Histoire	1	1	0	0	\N	\N	\N	2025-09-29 05:11:23	2025-10-11 05:11:23	\N	0	0	\N
44	3	1	completed	random	9	6	3	Sport	1	1	0	0	\N	\N	\N	2025-09-29 05:11:23	2025-10-09 05:11:23	\N	0	0	\N
45	3	1	completed	random	8	5	3	Sport	1	1	0	0	\N	\N	\N	2025-09-29 05:11:23	2025-10-06 05:11:23	\N	0	0	\N
46	3	1	completed	random	9	9	3	Histoire	1	1	0	0	\N	\N	\N	2025-10-11 05:11:23	2025-09-30 05:11:23	\N	0	0	\N
47	3	1	completed	random	9	5	3	Histoire	1	1	0	0	\N	\N	\N	2025-09-28 05:11:23	2025-09-29 05:11:23	\N	0	0	\N
48	3	1	completed	random	9	8	3	Sport	1	1	0	0	\N	\N	\N	2025-10-16 05:11:23	2025-10-03 05:11:23	\N	0	0	\N
49	3	1	completed	random	9	8	3	Culture	1	1	0	0	\N	\N	\N	2025-09-29 05:11:23	2025-09-30 05:11:23	\N	0	0	\N
50	3	1	completed	random	9	8	3	Science	1	1	0	0	\N	\N	\N	2025-10-14 05:11:23	2025-10-23 05:11:23	\N	0	0	\N
51	3	1	completed	random	9	6	3	Science	1	1	0	0	\N	\N	\N	2025-10-02 05:11:23	2025-10-07 05:11:23	\N	0	0	\N
52	3	1	completed	random	8	5	3	Sport	1	1	0	0	\N	\N	\N	2025-10-20 05:11:23	2025-10-19 05:11:23	\N	0	0	\N
53	3	1	completed	random	8	9	3	Géographie	1	1	0	0	\N	\N	\N	2025-10-24 05:11:23	2025-10-25 05:11:23	\N	0	0	\N
54	3	1	completed	random	8	6	3	Histoire	1	1	0	0	\N	\N	\N	2025-09-30 05:11:24	2025-10-24 05:11:24	\N	0	0	\N
55	3	1	completed	random	8	5	3	Culture	1	1	0	0	\N	\N	\N	2025-10-17 05:11:24	2025-10-13 05:11:24	\N	0	0	\N
56	3	1	completed	random	10	5	3	Culture	1	1	0	0	\N	\N	\N	2025-10-10 05:11:24	2025-10-03 05:11:24	\N	0	0	\N
57	3	1	completed	random	9	9	3	Géographie	1	1	0	0	\N	\N	\N	2025-10-13 05:11:24	2025-10-23 05:11:24	\N	0	0	\N
58	3	1	completed	random	9	7	3	Histoire	1	1	0	0	\N	\N	\N	2025-10-08 05:11:24	2025-10-17 05:11:24	\N	0	0	\N
59	3	1	completed	random	10	8	3	Science	1	1	0	0	\N	\N	\N	2025-10-22 05:11:24	2025-10-18 05:11:24	\N	0	0	\N
60	3	1	completed	random	8	6	3	Géographie	1	1	0	0	\N	\N	\N	2025-10-15 05:11:24	2025-10-22 05:11:24	\N	0	0	\N
61	3	1	completed	random	10	6	3	Culture	1	1	0	0	\N	\N	\N	2025-10-13 05:11:24	2025-10-21 05:11:24	\N	0	0	\N
62	3	1	completed	random	10	3	3	Sport	1	1	0	0	\N	\N	\N	2025-10-21 05:11:24	2025-10-13 05:11:24	\N	0	0	\N
63	3	1	completed	random	10	6	3	Culture	1	1	0	0	\N	\N	\N	2025-10-11 05:11:24	2025-10-19 05:11:24	\N	0	0	\N
64	3	1	completed	random	10	3	3	Culture	1	1	0	0	\N	\N	\N	2025-10-20 05:11:24	2025-10-18 05:11:24	\N	0	0	\N
65	3	1	completed	random	10	5	3	Culture	1	1	0	0	\N	\N	\N	2025-10-20 05:11:24	2025-10-17 05:11:24	\N	0	0	\N
130	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-21 22:02:50	\N	2025-12-21 22:00:07	2025-12-21 22:02:50	WWKKMY	0	0	\N
67	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	{"mode":"duo","theme":"general","nb_questions":10,"niveau":0,"current_round":1,"total_rounds":3,"players":[{"id":"player","user_id":3},{"id":"opponent","user_id":6}],"player_scores_map":{"player":0,"opponent":0},"player_rounds_won_map":{"player":0,"opponent":0},"player_stats_map":{"player":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0},"opponent":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0}},"score":0,"opponent_score":0,"player_rounds_won":0,"opponent_rounds_won":0,"player_total_score":0,"opponent_total_score":0,"current_question_number":1,"answered_questions":[],"used_question_ids":[],"current_question":null,"global_stats":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0},"match_result_processed":false,"used_skills":[],"game_started_at":"2025-12-02 00:20:09","buzzes":[],"question_start_time":1764634809.143949}	2025-12-02 00:20:08	\N	2025-12-01 20:40:57	2025-12-02 00:20:09	\N	0	0	\N
68	6	3	playing	invitation	0	0	\N	\N	0	0	0	0	{"mode":"duo","theme":"general","nb_questions":10,"niveau":0,"current_round":1,"total_rounds":3,"players":[{"id":"player","user_id":6},{"id":"opponent","user_id":3}],"player_scores_map":{"player":0,"opponent":0},"player_rounds_won_map":{"player":0,"opponent":0},"player_stats_map":{"player":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0},"opponent":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0}},"score":0,"opponent_score":0,"player_rounds_won":0,"opponent_rounds_won":0,"player_total_score":0,"opponent_total_score":0,"current_question_number":1,"answered_questions":[],"used_question_ids":[],"current_question":null,"global_stats":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0},"match_result_processed":false,"used_skills":[],"game_started_at":"2025-12-02 16:05:31","buzzes":[],"question_start_time":1764691531.327808}	2025-12-02 16:05:30	\N	2025-12-01 23:11:55	2025-12-02 16:05:31	\N	0	0	\N
132	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-21 22:58:33	\N	2025-12-21 22:57:37	2025-12-21 22:58:33	9YLF8Z	0	0	\N
69	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	{"mode":"duo","theme":"general","nb_questions":10,"niveau":0,"current_round":1,"total_rounds":3,"players":[{"id":"player","user_id":3},{"id":"opponent","user_id":6}],"player_scores_map":{"player":0,"opponent":0},"player_rounds_won_map":{"player":0,"opponent":0},"player_stats_map":{"player":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0},"opponent":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0}},"score":0,"opponent_score":0,"player_rounds_won":0,"opponent_rounds_won":0,"player_total_score":0,"opponent_total_score":0,"current_question_number":1,"answered_questions":[],"used_question_ids":[],"current_question":null,"global_stats":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0},"match_result_processed":false,"used_skills":[],"game_started_at":"2025-12-02 16:12:43","buzzes":[],"question_start_time":1764691963.875981}	2025-12-02 16:12:43	\N	2025-12-02 00:20:01	2025-12-02 16:12:43	\N	0	0	\N
66	3	8	cancelled	invitation	0	0	\N	\N	0	0	0	0	\N	\N	\N	2025-11-17 15:00:45	2025-12-03 00:38:24	\N	0	0	\N
151	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-27 21:44:56	\N	2025-12-27 21:40:35	2025-12-27 21:44:56	6RSG9D	0	0	\N
122	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-15 21:02:02	\N	2025-12-15 20:57:24	2025-12-15 21:02:02	ZC4J5M	0	0	\N
134	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-23 01:49:27	\N	2025-12-23 01:49:20	2025-12-23 01:49:27	5B37KN	0	0	\N
126	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-18 15:24:03	\N	2025-12-17 16:07:10	2025-12-18 15:24:03	KTVKLR	0	0	\N
127	3	9	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-20 02:13:19	\N	2025-12-20 02:10:30	2025-12-20 02:13:19	ZWJXLB	0	0	\N
144	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-27 02:06:45	\N	2025-12-27 01:32:54	2025-12-27 02:06:45	4VR5R8	0	0	\N
128	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-20 22:36:18	\N	2025-12-20 22:35:37	2025-12-20 22:36:18	T3J7C5	0	0	\N
137	4	3	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-24 01:53:49	\N	2025-12-24 01:52:23	2025-12-24 01:53:49	W4AFWK	0	0	\N
129	3	9	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-21 00:54:55	\N	2025-12-21 00:54:27	2025-12-21 00:54:55	3J9VW5	0	0	\N
139	4	3	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-24 02:55:41	\N	2025-12-24 02:55:09	2025-12-24 02:55:41	N59N83	0	0	\N
142	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-26 23:47:59	\N	2025-12-26 23:30:27	2025-12-26 23:47:59	YGGL6W	0	0	\N
146	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-27 02:25:54	\N	2025-12-27 02:23:14	2025-12-27 02:25:54	QAPH5B	0	0	\N
155	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-28 18:51:13	\N	2025-12-28 18:51:06	2025-12-28 18:51:13	ZAKS4L	0	0	\N
149	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-27 20:47:19	\N	2025-12-27 20:47:06	2025-12-27 20:47:19	NWUPS5	0	0	\N
153	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-27 22:03:52	\N	2025-12-27 22:01:23	2025-12-27 22:03:52	YB8KVU	0	0	\N
157	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-28 20:42:30	\N	2025-12-28 20:41:57	2025-12-28 20:42:30	GSH4GZ	0	0	\N
159	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-28 22:02:34	\N	2025-12-28 22:02:21	2025-12-28 22:02:34	TQ4NCE	0	0	\N
161	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-29 19:51:07	\N	2025-12-29 19:51:00	2025-12-29 19:51:07	X9WBQ7	0	0	\N
163	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-29 20:47:44	\N	2025-12-29 20:47:40	2025-12-29 20:47:44	KHF9F6	0	0	\N
165	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-29 23:53:16	\N	2025-12-29 23:53:09	2025-12-29 23:53:16	DGDDEB	0	0	\N
167	3	8	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-30 22:17:14	\N	2025-12-30 22:17:07	2025-12-30 22:17:14	HUNDC2	0	0	\N
169	3	8	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-31 01:16:33	\N	2025-12-31 01:16:27	2025-12-31 01:16:33	SGSXJJ	0	0	\N
196	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-05 19:48:02	\N	2026-01-05 19:47:17	2026-01-05 19:48:02	TMU35Y	0	0	\N
131	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-21 22:09:26	\N	2025-12-21 22:09:21	2025-12-21 22:09:26	EF6F43	0	0	\N
70	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	{"mode":"duo","theme":"general","nb_questions":10,"niveau":0,"current_round":1,"total_rounds":3,"players":[{"id":"player","user_id":3},{"id":"opponent","user_id":6}],"player_scores_map":{"player":0,"opponent":0},"player_rounds_won_map":{"player":0,"opponent":0},"player_stats_map":{"player":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0},"opponent":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0}},"score":0,"opponent_score":0,"player_rounds_won":0,"opponent_rounds_won":0,"player_total_score":0,"opponent_total_score":0,"current_question_number":1,"answered_questions":[],"used_question_ids":[],"current_question":null,"global_stats":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0},"match_result_processed":false,"used_skills":[],"game_started_at":"2025-12-02 16:13:50","buzzes":[],"question_start_time":1764692030.884418}	2025-12-02 16:13:50	\N	2025-12-02 16:12:20	2025-12-02 16:13:50	\N	0	0	\N
71	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	{"mode":"duo","theme":"general","nb_questions":10,"niveau":0,"current_round":1,"total_rounds":3,"players":[{"id":"player","user_id":3},{"id":"opponent","user_id":6}],"player_scores_map":{"player":0,"opponent":0},"player_rounds_won_map":{"player":0,"opponent":0},"player_stats_map":{"player":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0},"opponent":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0}},"score":0,"opponent_score":0,"player_rounds_won":0,"opponent_rounds_won":0,"player_total_score":0,"opponent_total_score":0,"current_question_number":1,"answered_questions":[],"used_question_ids":[],"current_question":null,"global_stats":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0},"match_result_processed":false,"used_skills":[],"game_started_at":"2025-12-02 16:54:35","buzzes":[],"question_start_time":1764694475.433319}	2025-12-02 16:54:34	\N	2025-12-02 16:53:49	2025-12-02 16:54:35	\N	0	0	\N
147	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-27 02:34:25	\N	2025-12-27 02:34:04	2025-12-27 02:34:25	HJYK8T	0	0	\N
72	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	{"mode":"duo","theme":"general","nb_questions":10,"niveau":0,"current_round":1,"total_rounds":3,"players":[{"id":"player","user_id":3},{"id":"opponent","user_id":6}],"player_scores_map":{"player":0,"opponent":0},"player_rounds_won_map":{"player":0,"opponent":0},"player_stats_map":{"player":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0},"opponent":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0}},"score":0,"opponent_score":0,"player_rounds_won":0,"opponent_rounds_won":0,"player_total_score":0,"opponent_total_score":0,"current_question_number":1,"answered_questions":[],"used_question_ids":[],"current_question":null,"global_stats":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0},"match_result_processed":false,"used_skills":[],"game_started_at":"2025-12-02 17:30:00","buzzes":[],"question_start_time":1764696600.514809}	2025-12-02 17:29:59	\N	2025-12-02 17:29:30	2025-12-02 17:30:00	\N	0	0	\N
123	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-15 21:10:11	\N	2025-12-15 21:10:03	2025-12-15 21:10:11	9XZ27U	0	0	\N
133	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-23 01:46:54	\N	2025-12-23 01:46:15	2025-12-23 01:46:54	BTETYA	0	0	\N
124	3	4	cancelled	invitation	0	0	\N	\N	0	0	0	0	\N	\N	\N	2025-12-16 04:09:50	2025-12-17 03:59:18	PEEZSM	0	0	\N
125	3	9	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-17 05:52:06	\N	2025-12-17 05:52:00	2025-12-17 05:52:06	592FB3	0	0	\N
135	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-23 01:58:44	\N	2025-12-23 01:57:01	2025-12-23 01:58:44	U3RSZV	0	0	\N
136	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-24 00:59:43	\N	2025-12-24 00:59:18	2025-12-24 00:59:43	LGLY5D	0	0	\N
148	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-27 18:34:39	\N	2025-12-27 17:47:31	2025-12-27 18:34:39	ACAN92	0	0	\N
138	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-24 01:55:53	\N	2025-12-24 01:55:48	2025-12-24 01:55:53	9UCLMU	0	0	\N
140	4	3	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-24 03:19:16	\N	2025-12-24 03:18:43	2025-12-24 03:19:16	CE7P7B	0	0	\N
158	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-28 21:57:57	\N	2025-12-28 21:57:52	2025-12-28 21:57:57	X6VS5N	0	0	\N
141	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-26 22:51:19	\N	2025-12-26 22:48:08	2025-12-26 22:51:19	V55JB4	0	0	\N
150	3	4	cancelled	invitation	0	0	\N	\N	0	0	0	0	\N	\N	\N	2025-12-27 21:39:11	2025-12-27 21:40:19	AAYMHA	0	0	\N
143	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-27 00:33:34	\N	2025-12-27 00:31:41	2025-12-27 00:33:34	WVSBPR	0	0	\N
145	4	3	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-27 02:10:36	\N	2025-12-27 02:09:47	2025-12-27 02:10:36	AZEQB5	0	0	\N
152	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-27 22:00:16	\N	2025-12-27 21:45:34	2025-12-27 22:00:16	UG7BAU	0	0	\N
170	3	8	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-31 01:29:07	\N	2025-12-31 01:29:00	2025-12-31 01:29:07	UFEH43	0	0	\N
154	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-28 18:42:32	\N	2025-12-28 18:42:29	2025-12-28 18:42:32	4W7V7Z	0	0	\N
160	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-29 18:44:56	\N	2025-12-29 18:44:47	2025-12-29 18:44:56	CQ2FT2	0	0	\N
156	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-28 20:24:31	\N	2025-12-28 20:24:25	2025-12-28 20:24:31	JNMZ3Y	0	0	\N
166	3	8	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-30 22:02:34	\N	2025-12-30 22:02:25	2025-12-30 22:02:34	XSYWSD	0	0	\N
162	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-29 20:16:32	\N	2025-12-29 20:16:18	2025-12-29 20:16:32	WD8VNX	0	0	\N
164	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-29 22:32:07	\N	2025-12-29 22:32:03	2025-12-29 22:32:07	EMH8MB	0	0	\N
171	3	8	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-31 15:39:54	\N	2025-12-31 15:39:33	2025-12-31 15:39:54	UN8LM7	0	0	\N
168	3	8	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-31 00:36:37	\N	2025-12-31 00:36:31	2025-12-31 00:36:37	T2ZK8Y	0	0	\N
172	3	8	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-31 17:02:25	\N	2025-12-31 17:02:19	2025-12-31 17:02:25	Q7PXN5	0	0	\N
173	3	8	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-31 17:17:25	\N	2025-12-31 17:17:17	2025-12-31 17:17:25	EY7TEG	0	0	\N
174	3	8	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-31 18:18:35	\N	2025-12-31 18:18:29	2025-12-31 18:18:35	NRKYW8	0	0	\N
175	3	8	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-31 18:43:10	\N	2025-12-31 18:42:59	2025-12-31 18:43:10	2NCGRJ	0	0	\N
176	3	8	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-31 19:01:03	\N	2025-12-31 19:00:58	2025-12-31 19:01:03	58RKJ9	0	0	\N
177	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-02 16:06:53	\N	2026-01-02 13:57:16	2026-01-02 16:06:53	2J9HZ4	0	0	\N
178	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-02 16:07:38	\N	2026-01-02 16:07:32	2026-01-02 16:07:38	CEVRXU	0	0	\N
94	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-06 17:52:41	\N	2025-12-06 17:52:34	2025-12-06 17:52:41	6BZFC2	0	0	\N
73	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	{"mode":"duo","theme":"general","nb_questions":10,"niveau":0,"current_round":1,"total_rounds":3,"players":[{"id":"player","user_id":3},{"id":"opponent","user_id":6}],"player_scores_map":{"player":0,"opponent":0},"player_rounds_won_map":{"player":0,"opponent":0},"player_stats_map":{"player":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0},"opponent":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0}},"score":0,"opponent_score":0,"player_rounds_won":0,"opponent_rounds_won":0,"player_total_score":0,"opponent_total_score":0,"current_question_number":1,"answered_questions":[],"used_question_ids":[],"current_question":null,"global_stats":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0},"match_result_processed":false,"used_skills":[],"game_started_at":"2025-12-02 17:47:30","buzzes":[],"question_start_time":1764697650.995416}	2025-12-02 17:47:30	\N	2025-12-02 17:46:49	2025-12-02 17:47:30	\N	0	0	\N
84	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-03 00:42:53	\N	2025-12-03 00:42:47	2025-12-03 00:42:53	RAEA29	0	0	\N
74	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-02 18:18:02	\N	2025-12-02 18:11:15	2025-12-02 18:18:02	A6QLBX	0	0	\N
75	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-02 18:18:22	\N	2025-12-02 18:18:20	2025-12-02 18:18:23	W97A49	0	0	\N
76	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-02 18:32:01	\N	2025-12-02 18:31:55	2025-12-02 18:32:01	VJ3ZLH	0	0	\N
77	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-02 18:34:20	\N	2025-12-02 18:34:14	2025-12-02 18:34:21	SY78EK	0	0	\N
85	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-03 00:49:57	\N	2025-12-03 00:49:49	2025-12-03 00:49:57	S8F2MD	0	0	\N
108	3	8	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-11 13:29:03	\N	2025-12-11 13:22:58	2025-12-11 13:29:03	RFLBKY	0	0	\N
86	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-05 23:02:12	\N	2025-12-05 23:02:00	2025-12-05 23:02:12	9DGWZP	0	0	\N
78	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-02 22:52:54	\N	2025-12-02 19:19:52	2025-12-02 22:52:54	6TXH3X	0	0	\N
79	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-02 23:52:02	\N	2025-12-02 19:20:05	2025-12-02 23:52:02	2W879D	0	0	\N
80	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-02 23:52:14	\N	2025-12-02 19:24:06	2025-12-02 23:52:14	ML3LVS	0	0	\N
81	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-02 23:53:59	\N	2025-12-02 22:51:04	2025-12-02 23:53:59	PSGXG6	0	0	\N
95	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-06 18:22:59	\N	2025-12-06 18:21:34	2025-12-06 18:22:59	MBHMUS	0	0	\N
83	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-03 00:11:05	\N	2025-12-03 00:09:24	2025-12-03 00:11:05	53GXN4	0	0	\N
82	3	6	cancelled	invitation	0	0	\N	\N	0	0	0	0	\N	\N	\N	2025-12-02 23:53:38	2025-12-03 00:38:32	BK48DA	0	0	\N
87	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-05 23:06:29	\N	2025-12-05 23:06:19	2025-12-05 23:06:29	NHB5FC	0	0	\N
88	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-05 23:38:06	\N	2025-12-05 23:37:30	2025-12-05 23:38:06	KJ73L4	0	0	\N
103	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-10 01:15:54	\N	2025-12-10 01:14:50	2025-12-10 01:15:54	E3SVUP	0	0	\N
89	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-06 00:13:50	\N	2025-12-06 00:13:32	2025-12-06 00:13:50	SFNMGS	0	0	\N
96	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-06 18:41:41	\N	2025-12-06 18:38:41	2025-12-06 18:41:41	FBY5J2	0	0	\N
90	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-06 00:32:55	\N	2025-12-06 00:30:32	2025-12-06 00:32:55	TBBNHL	0	0	\N
97	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-06 18:54:33	\N	2025-12-06 18:53:20	2025-12-06 18:54:33	WDVUFU	0	0	\N
91	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-06 01:48:28	\N	2025-12-06 01:08:44	2025-12-06 01:48:28	USDT3J	0	0	\N
92	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-06 17:37:14	\N	2025-12-06 17:12:06	2025-12-06 17:37:14	5G59AR	0	0	\N
98	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-06 19:32:12	\N	2025-12-06 19:27:32	2025-12-06 19:32:12	XA3ETR	0	0	\N
93	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-06 17:44:12	\N	2025-12-06 17:43:37	2025-12-06 17:44:12	XC8T6C	0	0	\N
104	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-10 01:54:18	\N	2025-12-10 01:53:21	2025-12-10 01:54:18	H4XRWN	0	0	\N
99	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-06 21:35:41	\N	2025-12-06 21:33:50	2025-12-06 21:35:41	49S8HC	0	0	\N
100	3	6	cancelled	invitation	0	0	\N	\N	0	0	0	0	\N	\N	\N	2025-12-06 21:55:48	2025-12-06 21:56:06	NX6XAB	0	0	\N
105	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-10 02:55:04	\N	2025-12-10 02:54:44	2025-12-10 02:55:04	LWC6PD	0	0	\N
101	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-06 21:57:23	\N	2025-12-06 21:56:27	2025-12-06 21:57:23	JSECYN	0	0	\N
102	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-09 23:49:18	\N	2025-12-09 23:38:19	2025-12-09 23:49:18	9XBNFX	0	0	\N
109	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-11 22:36:28	\N	2025-12-11 21:45:45	2025-12-11 22:36:28	LE9ZRX	0	0	\N
106	3	4	cancelled	invitation	0	0	\N	\N	0	0	0	0	\N	\N	\N	2025-12-10 12:59:54	2025-12-11 13:14:56	CVGNJH	0	0	\N
107	3	4	cancelled	invitation	0	0	\N	\N	0	0	0	0	\N	\N	\N	2025-12-10 13:08:19	2025-12-11 13:15:00	28GMTP	0	0	\N
115	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-15 01:26:03	\N	2025-12-15 01:24:59	2025-12-15 01:26:03	BY2LX9	0	0	\N
112	3	4	cancelled	invitation	0	0	\N	\N	0	0	0	0	\N	\N	\N	2025-12-12 05:30:09	2025-12-12 08:18:01	EV28TE	0	0	\N
110	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-11 22:47:24	\N	2025-12-11 22:47:03	2025-12-11 22:47:24	5HKALD	0	0	\N
111	3	4	cancelled	invitation	0	0	\N	\N	0	0	0	0	\N	\N	\N	2025-12-12 04:02:58	2025-12-12 05:29:56	ZA3ZFX	0	0	\N
114	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-15 00:56:46	\N	2025-12-15 00:37:38	2025-12-15 00:56:46	75ZP4U	0	0	\N
113	3	4	cancelled	invitation	0	0	\N	\N	0	0	0	0	\N	\N	\N	2025-12-12 08:18:08	2025-12-13 19:24:48	X73A2M	0	0	\N
116	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-15 01:33:20	\N	2025-12-15 01:32:59	2025-12-15 01:33:20	HA2PJX	0	0	\N
117	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-15 01:36:45	\N	2025-12-15 01:36:22	2025-12-15 01:36:45	KV9P94	0	0	\N
118	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-15 02:48:44	\N	2025-12-15 02:48:12	2025-12-15 02:48:44	Q92VU5	0	0	\N
119	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2025-12-15 02:51:07	\N	2025-12-15 02:51:01	2025-12-15 02:51:07	S7RXJZ	0	0	\N
120	3	4	cancelled	invitation	0	0	\N	\N	0	0	0	0	\N	\N	\N	2025-12-15 03:10:36	2025-12-15 19:49:47	84AKT6	0	0	\N
121	3	4	cancelled	invitation	0	0	\N	\N	0	0	0	0	\N	\N	\N	2025-12-15 19:49:56	2025-12-15 20:57:14	J6K39X	0	0	\N
195	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-05 19:29:04	\N	2026-01-05 19:27:55	2026-01-05 19:29:04	6FLPX4	0	0	\N
179	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-02 19:06:53	\N	2026-01-02 19:06:42	2026-01-02 19:06:53	J7H57K	0	0	\N
180	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-02 20:36:14	\N	2026-01-02 20:36:03	2026-01-02 20:36:14	2MCZ52	0	0	\N
197	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-05 20:37:38	\N	2026-01-05 20:36:21	2026-01-05 20:37:38	T3WFLX	0	0	\N
198	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-05 20:41:00	\N	2026-01-05 20:40:27	2026-01-05 20:41:00	SCM2JN	0	0	\N
181	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-02 20:50:33	\N	2026-01-02 20:50:28	2026-01-02 20:50:33	92CT9K	0	0	\N
199	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-05 22:15:24	\N	2026-01-05 22:13:17	2026-01-05 22:15:24	TZEULY	0	0	\N
200	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-05 22:41:26	\N	2026-01-05 22:41:09	2026-01-05 22:41:26	QYW2ET	0	0	\N
182	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-03 23:34:40	\N	2026-01-03 23:34:33	2026-01-03 23:34:40	RW4ZTP	0	0	\N
201	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-06 19:25:09	\N	2026-01-06 19:18:43	2026-01-06 19:25:09	QC6WJ6	0	0	\N
202	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-07 21:25:51	\N	2026-01-07 21:25:05	2026-01-07 21:25:51	T2CSS5	0	0	\N
183	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-04 00:31:08	\N	2026-01-04 00:31:03	2026-01-04 00:31:08	PX5J23	0	0	\N
203	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-07 21:49:21	\N	2026-01-07 21:49:15	2026-01-07 21:49:21	GZDPKK	0	0	\N
204	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-08 02:34:10	\N	2026-01-08 02:34:03	2026-01-08 02:34:10	C3AB9J	0	0	\N
184	3	6	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-04 05:17:56	\N	2026-01-04 05:16:17	2026-01-04 05:17:56	TDKYYB	0	0	\N
205	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-08 05:31:06	\N	2026-01-08 05:31:04	2026-01-08 05:31:06	2XT2EN	0	0	\N
206	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-08 05:32:01	\N	2026-01-08 05:31:57	2026-01-08 05:32:01	9B86KL	0	0	\N
185	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-04 20:36:08	\N	2026-01-04 20:35:56	2026-01-04 20:36:08	49UD65	0	0	\N
186	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-04 21:09:12	\N	2026-01-04 21:09:03	2026-01-04 21:09:12	TKPJBJ	0	0	\N
207	3	4	in_progress	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-08 05:52:58	\N	2026-01-08 05:52:16	2026-01-08 05:52:58	XUR4LJ	0	0	af8480cd-dfea-4242-acbd-f4c8ac1ca0fd
187	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-04 21:54:06	\N	2026-01-04 21:53:57	2026-01-04 21:54:06	4BMJD6	0	0	\N
208	3	4	in_progress	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-10 21:38:00	\N	2026-01-10 21:31:09	2026-01-10 21:38:00	3V9PQS	0	0	83b1f1ea-effe-413a-b6d5-3444847842d6
188	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-05 00:27:06	\N	2026-01-05 00:26:51	2026-01-05 00:27:06	B93Q8Z	0	0	\N
209	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-18 03:01:45	\N	2026-01-17 04:52:51	2026-01-18 03:01:45	4AK3JM	0	0	\N
189	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-05 00:34:12	\N	2026-01-05 00:34:04	2026-01-05 00:34:12	T7QHZG	0	0	\N
213	3	4	in_progress	invitation	0	0	\N	\N	0	0	0	0	\N	2026-02-04 15:07:20	\N	2026-02-04 15:00:00	2026-02-04 15:07:20	PJLL43	0	0	6d0d8051-7dcb-4c7d-a3fe-4ef350ec4fb7
190	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-05 00:40:24	\N	2026-01-05 00:40:15	2026-01-05 00:40:24	J8MTC7	0	0	\N
211	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-18 03:44:58	\N	2026-01-18 03:44:51	2026-01-18 03:44:58	XSF42L	0	0	\N
191	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-05 17:13:40	\N	2026-01-05 17:13:32	2026-01-05 17:13:40	RAQEH7	0	0	\N
210	3	4	in_progress	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-18 03:45:26	\N	2026-01-18 03:03:41	2026-01-18 03:45:26	WDL6XE	0	0	8dfb356e-4277-4636-9ac9-77298bfb10d2
212	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-18 12:34:22	\N	2026-01-18 12:33:35	2026-01-18 12:34:22	JTFYZ9	0	0	\N
192	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-05 18:18:30	\N	2026-01-05 18:18:20	2026-01-05 18:18:30	PLEL58	0	0	\N
193	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-05 18:27:51	\N	2026-01-05 18:27:46	2026-01-05 18:27:51	3VJM4M	0	0	\N
194	3	4	playing	invitation	0	0	\N	\N	0	0	0	0	\N	2026-01-05 18:32:02	\N	2026-01-05 18:31:58	2026-01-05 18:32:02	Z998FH	0	0	\N
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
1	3eb3a73b-d178-4856-94db-0a2262fffa8c	database	default	{"uuid":"3eb3a73b-d178-4856-94db-0a2262fffa8c","displayName":"App\\\\Jobs\\\\GenerateMultiplayerQuestionsJob","job":"Illuminate\\\\Queue\\\\CallQueuedHandler@call","maxTries":3,"maxExceptions":2,"failOnTimeout":false,"backoff":null,"timeout":300,"retryUntil":null,"data":{"commandName":"App\\\\Jobs\\\\GenerateMultiplayerQuestionsJob","command":"O:40:\\"App\\\\Jobs\\\\GenerateMultiplayerQuestionsJob\\":15:{s:9:\\"\\u0000*\\u0000roomId\\";s:6:\\"J8MTC7\\";s:7:\\"\\u0000*\\u0000mode\\";s:3:\\"duo\\";s:8:\\"\\u0000*\\u0000theme\\";s:18:\\"Culture générale\\";s:9:\\"\\u0000*\\u0000niveau\\";i:1;s:11:\\"\\u0000*\\u0000language\\";s:2:\\"fr\\";s:17:\\"\\u0000*\\u0000totalQuestions\\";i:10;s:13:\\"\\u0000*\\u0000startIndex\\";i:2;s:11:\\"\\u0000*\\u0000blocSize\\";i:4;s:18:\\"\\u0000*\\u0000usedQuestionIds\\";a:1:{i:0;s:54:\\"Culture générale_ai_f23a84116b1e668abd29f83541c8440d\\";}s:14:\\"\\u0000*\\u0000usedAnswers\\";a:4:{i:0;s:6:\\"Girafe\\";i:1;s:8:\\"Marmotte\\";i:2;s:4:\\"Lion\\";i:3;s:10:\\"Éléphant\\";}s:20:\\"\\u0000*\\u0000usedQuestionTexts\\";a:0:{}s:20:\\"\\u0000*\\u0000includeSkillBonus\\";b:0;s:20:\\"\\u0000*\\u0000includeTiebreaker\\";b:1;s:16:\\"\\u0000*\\u0000mainQuestions\\";i:10;s:22:\\"\\u0000*\\u0000skillBonusQuestions\\";i:0;}"}}	Error: Class "Redis" not found in /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Redis/Connectors/PhpRedisConnector.php:79\nStack trace:\n#0 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Redis/Connectors/PhpRedisConnector.php(33): Illuminate\\Redis\\Connectors\\PhpRedisConnector->createClient(Array)\n#1 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Redis/Connectors/PhpRedisConnector.php(38): Illuminate\\Redis\\Connectors\\PhpRedisConnector->Illuminate\\Redis\\Connectors\\{closure}()\n#2 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Redis/RedisManager.php(110): Illuminate\\Redis\\Connectors\\PhpRedisConnector->connect(Array, Array)\n#3 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Redis/RedisManager.php(91): Illuminate\\Redis\\RedisManager->resolve('default')\n#4 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Redis/RedisManager.php(276): Illuminate\\Redis\\RedisManager->connection()\n#5 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Support/Facades/Facade.php(355): Illuminate\\Redis\\RedisManager->__call('exists', Array)\n#6 /home/runner/workspace/app/Services/AntiDuplicationCacheService.php(112): Illuminate\\Support\\Facades\\Facade::__callStatic('exists', Array)\n#7 /home/runner/workspace/app/Jobs/GenerateMultiplayerQuestionsJob.php(103): App\\Services\\AntiDuplicationCacheService->exists('J8MTC7')\n#8 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): App\\Jobs\\GenerateMultiplayerQuestionsJob->handle()\n#9 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Container/Util.php(41): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#10 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(93): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#11 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#12 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Container/Container.php(662): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#13 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(128): Illuminate\\Container\\Container->call(Array)\n#14 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(144): Illuminate\\Bus\\Dispatcher->Illuminate\\Bus\\{closure}(Object(App\\Jobs\\GenerateMultiplayerQuestionsJob))\n#15 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(119): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\GenerateMultiplayerQuestionsJob))\n#16 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(132): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#17 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(123): Illuminate\\Bus\\Dispatcher->dispatchNow(Object(App\\Jobs\\GenerateMultiplayerQuestionsJob), false)\n#18 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(144): Illuminate\\Queue\\CallQueuedHandler->Illuminate\\Queue\\{closure}(Object(App\\Jobs\\GenerateMultiplayerQuestionsJob))\n#19 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(119): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\GenerateMultiplayerQuestionsJob))\n#20 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(122): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#21 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(70): Illuminate\\Queue\\CallQueuedHandler->dispatchThroughMiddleware(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(App\\Jobs\\GenerateMultiplayerQuestionsJob))\n#22 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(102): Illuminate\\Queue\\CallQueuedHandler->call(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Array)\n#23 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(439): Illuminate\\Queue\\Jobs\\Job->fire()\n#24 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(389): Illuminate\\Queue\\Worker->process('database', Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Queue\\WorkerOptions))\n#25 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(176): Illuminate\\Queue\\Worker->runJob(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), 'database', Object(Illuminate\\Queue\\WorkerOptions))\n#26 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(137): Illuminate\\Queue\\Worker->daemon('database', 'default', Object(Illuminate\\Queue\\WorkerOptions))\n#27 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(120): Illuminate\\Queue\\Console\\WorkCommand->runWorker('database', 'default')\n#28 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Queue\\Console\\WorkCommand->handle()\n#29 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Container/Util.php(41): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#30 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(93): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#31 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#32 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Container/Container.php(662): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#33 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Console/Command.php(211): Illuminate\\Container\\Container->call(Array)\n#34 /home/runner/workspace/vendor/symfony/console/Command/Command.php(326): Illuminate\\Console\\Command->execute(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#35 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Console/Command.php(180): Symfony\\Component\\Console\\Command\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#36 /home/runner/workspace/vendor/symfony/console/Application.php(1096): Illuminate\\Console\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#37 /home/runner/workspace/vendor/symfony/console/Application.php(324): Symfony\\Component\\Console\\Application->doRunCommand(Object(Illuminate\\Queue\\Console\\WorkCommand), Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#38 /home/runner/workspace/vendor/symfony/console/Application.php(175): Symfony\\Component\\Console\\Application->doRun(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#39 /home/runner/workspace/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(201): Symfony\\Component\\Console\\Application->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#40 /home/runner/workspace/artisan(14): Illuminate\\Foundation\\Console\\Kernel->handle(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#41 {main}	2026-01-05 00:41:04
\.


--
-- Data for Name: invitations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.invitations (id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: league_individual_matches; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.league_individual_matches (id, player1_id, player2_id, winner_id, status, player1_level, player2_level, game_state, player1_points_earned, player2_points_earned, created_at, updated_at, player1_coins_earned, player2_coins_earned) FROM stdin;
1	3	4	\N	playing	1	1	{"mode":"league_individual","theme":"culture","nb_questions":10,"niveau":1,"current_round":1,"total_rounds":3,"players":[{"id":"player"},{"id":"opponent"}],"player_scores_map":{"player":0,"opponent":0},"player_rounds_won_map":{"player":0,"opponent":0},"player_stats_map":{"player":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0},"opponent":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0}},"score":0,"opponent_score":0,"player_rounds_won":0,"opponent_rounds_won":0,"player_total_score":0,"opponent_total_score":0,"current_question_number":1,"answered_questions":[],"used_question_ids":[],"current_question":null,"global_stats":{"correct":0,"incorrect":0,"unanswered":0,"total_points":0},"match_result_processed":false,"used_skills":[],"game_started_at":"2025-12-20 10:11:00"}	0	0	2025-12-20 10:11:00	2025-12-20 10:11:00	0	0
\.


--
-- Data for Name: league_individual_stats; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.league_individual_stats (id, user_id, level, matches_played, matches_won, matches_lost, total_points, initialized, created_at, updated_at) FROM stdin;
1	3	1	0	0	0	0	t	2025-10-13 00:06:15	2025-10-13 00:06:15
2	4	1	0	0	0	0	t	2025-12-09 17:27:33	2025-12-09 17:27:34
\.


--
-- Data for Name: league_team_match_participants; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.league_team_match_participants (id, match_id, team_id, user_id, score, correct_answers, wrong_answers, buzzes, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: league_team_matches; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.league_team_matches (id, team1_id, team2_id, team1_level, team2_level, winner_team_id, status, game_state, team1_points_earned, team2_points_earned, created_at, updated_at, game_mode, player_order, duel_pairings, relay_indices, completed_at, match_division) FROM stdin;
\.


--
-- Data for Name: lobby_game_starts; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.lobby_game_starts (id, lobby_code, bet_amount, refunded_at, refund_reason, created_at) FROM stdin;
4ceb08ee-8ec0-4719-b723-5768aceaa5c8	LGLY5D	5	\N	\N	2025-12-24 01:00:34
\.


--
-- Data for Name: master_game_codes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.master_game_codes (id, master_game_id, code, role, side, capacity, used, state, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: master_game_players; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.master_game_players (id, master_game_id, user_id, master_game_code_id, side, score, answered, status, created_at, updated_at, team_id, seat_index, is_captain) FROM stdin;
\.


--
-- Data for Name: master_game_questions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.master_game_questions (id, master_game_id, question_number, type, text, choices, correct_indexes, media_url, created_at, updated_at, is_tiebreaker) FROM stdin;
3	22	1	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-10-20 13:21:06	2025-10-20 13:21:06	f
4	22	2	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-10-20 13:21:06	2025-10-20 13:21:06	f
5	22	3	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-10-20 13:21:07	2025-10-20 13:21:07	f
6	22	4	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-10-20 13:21:07	2025-10-20 13:21:07	f
7	22	5	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-10-20 13:21:07	2025-10-20 13:21:07	f
8	22	6	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-10-20 13:21:08	2025-10-20 13:21:08	f
9	22	7	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-10-20 13:21:08	2025-10-20 13:21:08	f
10	22	8	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-10-20 13:21:08	2025-10-20 13:21:08	f
11	22	9	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-10-20 13:21:08	2025-10-20 13:21:08	f
12	22	10	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-10-20 13:21:09	2025-10-20 13:21:09	f
13	23	1	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:30	2025-11-27 00:14:30	f
14	23	2	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:30	2025-11-27 00:14:30	f
15	23	3	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:31	2025-11-27 00:14:31	f
16	23	4	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:32	2025-11-27 00:14:32	f
17	23	5	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:33	2025-11-27 00:14:33	f
18	23	6	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:33	2025-11-27 00:14:33	f
19	23	7	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:34	2025-11-27 00:14:34	f
20	23	8	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:34	2025-11-27 00:14:34	f
21	23	9	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:35	2025-11-27 00:14:35	f
22	23	10	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:35	2025-11-27 00:14:35	f
23	24	1	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:35	2025-11-27 00:14:35	f
24	24	2	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:36	2025-11-27 00:14:36	f
25	24	3	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:36	2025-11-27 00:14:36	f
26	24	4	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:36	2025-11-27 00:14:36	f
27	24	5	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:36	2025-11-27 00:14:36	f
28	24	6	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:36	2025-11-27 00:14:36	f
29	24	7	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:36	2025-11-27 00:14:36	f
30	24	8	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:37	2025-11-27 00:14:37	f
31	24	9	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:37	2025-11-27 00:14:37	f
32	24	10	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:37	2025-11-27 00:14:37	f
33	25	1	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:39	2025-11-27 00:14:39	f
34	25	2	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:39	2025-11-27 00:14:39	f
35	25	3	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:39	2025-11-27 00:14:39	f
36	25	4	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:40	2025-11-27 00:14:40	f
37	25	5	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:40	2025-11-27 00:14:40	f
38	25	6	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:40	2025-11-27 00:14:40	f
39	25	7	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:40	2025-11-27 00:14:40	f
40	25	8	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:40	2025-11-27 00:14:40	f
41	25	9	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:40	2025-11-27 00:14:40	f
42	25	10	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:41	2025-11-27 00:14:41	f
43	27	1	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:44	2025-11-27 00:14:44	f
44	27	2	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:44	2025-11-27 00:14:44	f
45	27	3	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:44	2025-11-27 00:14:44	f
46	27	4	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:44	2025-11-27 00:14:44	f
47	27	5	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:45	2025-11-27 00:14:45	f
48	27	6	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:45	2025-11-27 00:14:45	f
49	27	7	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:45	2025-11-27 00:14:45	f
50	27	8	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:45	2025-11-27 00:14:45	f
51	27	9	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:46	2025-11-27 00:14:46	f
52	27	10	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:46	2025-11-27 00:14:46	f
53	28	1	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:47	2025-11-27 00:14:47	f
54	28	2	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:48	2025-11-27 00:14:48	f
55	28	3	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:48	2025-11-27 00:14:48	f
56	28	4	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:48	2025-11-27 00:14:48	f
57	28	5	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:48	2025-11-27 00:14:48	f
58	28	6	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:48	2025-11-27 00:14:48	f
59	28	7	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:48	2025-11-27 00:14:48	f
60	28	8	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:49	2025-11-27 00:14:49	f
61	28	9	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:49	2025-11-27 00:14:49	f
62	28	10	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:49	2025-11-27 00:14:49	f
63	29	1	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:50	2025-11-27 00:14:50	f
64	29	2	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:50	2025-11-27 00:14:50	f
65	29	3	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:50	2025-11-27 00:14:50	f
66	29	4	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:50	2025-11-27 00:14:50	f
67	29	5	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:50	2025-11-27 00:14:50	f
68	29	6	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:50	2025-11-27 00:14:50	f
69	29	7	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:50	2025-11-27 00:14:50	f
70	29	8	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:51	2025-11-27 00:14:51	f
71	29	9	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:51	2025-11-27 00:14:51	f
72	29	10	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:51	2025-11-27 00:14:51	f
73	30	1	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:52	2025-11-27 00:14:52	f
74	30	2	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:52	2025-11-27 00:14:52	f
75	30	3	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:52	2025-11-27 00:14:52	f
76	30	4	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:52	2025-11-27 00:14:52	f
77	30	5	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:52	2025-11-27 00:14:52	f
78	30	6	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:52	2025-11-27 00:14:52	f
79	30	7	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:52	2025-11-27 00:14:52	f
80	30	8	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:53	2025-11-27 00:14:53	f
81	30	9	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:53	2025-11-27 00:14:53	f
82	30	10	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:53	2025-11-27 00:14:53	f
83	31	1	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:54	2025-11-27 00:14:54	f
84	31	2	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:54	2025-11-27 00:14:54	f
85	31	3	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:54	2025-11-27 00:14:54	f
86	31	4	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:54	2025-11-27 00:14:54	f
87	31	5	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:54	2025-11-27 00:14:54	f
88	31	6	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:54	2025-11-27 00:14:54	f
89	31	7	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:55	2025-11-27 00:14:55	f
90	31	8	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:55	2025-11-27 00:14:55	f
91	31	9	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:55	2025-11-27 00:14:55	f
92	31	10	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:55	2025-11-27 00:14:55	f
93	32	1	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:56	2025-11-27 00:14:56	f
94	32	2	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:56	2025-11-27 00:14:56	f
95	32	3	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:56	2025-11-27 00:14:56	f
96	32	4	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:56	2025-11-27 00:14:56	f
97	32	5	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:56	2025-11-27 00:14:56	f
98	32	6	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:57	2025-11-27 00:14:57	f
99	32	7	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:57	2025-11-27 00:14:57	f
100	32	8	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:57	2025-11-27 00:14:57	f
101	32	9	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:14:57	2025-11-27 00:14:57	f
102	32	10	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:14:57	2025-11-27 00:14:57	f
103	33	1	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:28	2025-11-27 00:47:28	f
104	33	2	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:28	2025-11-27 00:47:28	f
105	33	3	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:28	2025-11-27 00:47:28	f
106	33	4	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:29	2025-11-27 00:47:29	f
107	33	5	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:29	2025-11-27 00:47:29	f
108	33	6	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:29	2025-11-27 00:47:29	f
109	33	7	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:29	2025-11-27 00:47:29	f
110	33	8	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:29	2025-11-27 00:47:29	f
111	33	9	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:30	2025-11-27 00:47:30	f
112	33	10	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:30	2025-11-27 00:47:30	f
113	34	1	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:33	2025-11-27 00:47:33	f
114	34	2	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:33	2025-11-27 00:47:33	f
115	34	3	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:33	2025-11-27 00:47:33	f
116	34	4	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:34	2025-11-27 00:47:34	f
117	34	5	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:34	2025-11-27 00:47:34	f
118	34	6	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:34	2025-11-27 00:47:34	f
119	34	7	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:34	2025-11-27 00:47:34	f
120	34	8	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:34	2025-11-27 00:47:34	f
121	34	9	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:35	2025-11-27 00:47:35	f
122	34	10	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:47:35	2025-11-27 00:47:35	f
123	35	1	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:53:46	2025-11-27 00:53:46	f
124	35	2	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:53:46	2025-11-27 00:53:46	f
125	35	3	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:53:46	2025-11-27 00:53:46	f
126	35	4	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:53:46	2025-11-27 00:53:46	f
127	35	5	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:53:46	2025-11-27 00:53:46	f
128	35	6	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:53:47	2025-11-27 00:53:47	f
129	35	7	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:53:47	2025-11-27 00:53:47	f
130	35	8	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:53:47	2025-11-27 00:53:47	f
131	35	9	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:53:47	2025-11-27 00:53:47	f
132	35	10	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:53:47	2025-11-27 00:53:47	f
133	35	11	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:53:47	2025-11-27 00:53:47	f
134	35	12	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:53:47	2025-11-27 00:53:47	f
135	35	13	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:53:48	2025-11-27 00:53:48	f
136	35	14	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:53:48	2025-11-27 00:53:48	f
137	35	15	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:53:48	2025-11-27 00:53:48	f
138	35	16	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:53:48	2025-11-27 00:53:48	f
139	35	17	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:53:48	2025-11-27 00:53:48	f
140	35	18	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:53:48	2025-11-27 00:53:48	f
141	35	19	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 00:53:48	2025-11-27 00:53:48	f
142	35	20	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 00:53:49	2025-11-27 00:53:49	f
143	36	1	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 01:42:38	2025-11-27 01:42:38	f
145	37	1	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 01:50:30	2025-11-27 01:50:30	f
146	37	2	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 01:50:30	2025-11-27 01:50:30	f
147	37	3	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 01:50:30	2025-11-27 01:50:30	f
148	37	4	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 01:50:31	2025-11-27 01:50:31	f
149	37	5	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 01:50:31	2025-11-27 01:50:31	f
150	37	6	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 01:50:31	2025-11-27 01:50:31	f
151	37	7	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 01:50:31	2025-11-27 01:50:31	f
152	37	8	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 01:50:31	2025-11-27 01:50:31	f
153	37	9	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 01:50:32	2025-11-27 01:50:32	f
154	37	10	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 01:50:32	2025-11-27 01:50:32	f
155	37	11	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 01:50:32	2025-11-27 01:50:32	f
156	37	12	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 01:50:32	2025-11-27 01:50:32	f
157	37	13	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 01:50:32	2025-11-27 01:50:32	f
158	37	14	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 01:50:32	2025-11-27 01:50:32	f
159	37	15	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 01:50:33	2025-11-27 01:50:33	f
160	37	16	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 01:50:33	2025-11-27 01:50:33	f
161	37	17	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 01:50:33	2025-11-27 01:50:33	f
162	37	18	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 01:50:33	2025-11-27 01:50:33	f
163	37	19	true_false	Question à compléter	["Vrai","Faux"]	[0]	\N	2025-11-27 01:50:33	2025-11-27 01:50:33	f
164	37	20	multiple_choice	Question à compléter	["R\\u00e9ponse 1","R\\u00e9ponse 2","R\\u00e9ponse 3","R\\u00e9ponse 4"]	[0]	\N	2025-11-27 01:50:33	2025-11-27 01:50:33	f
165	38	1	true_false	Le Nil est le fleuve le plus long du monde.	["Vrai","Faux"]	[1]	\N	2025-11-27 02:05:29	2025-11-27 02:05:29	f
166	38	2	true_false	Le Mont Everest est la plus haute montagne du monde.	["Vrai","Faux"]	[0]	\N	2025-11-27 02:05:30	2025-11-27 02:05:30	f
167	38	3	true_false	La France est le plus grand pays de l'Union européenne en termes de superficie.	["Vrai","Faux"]	[0]	\N	2025-11-27 02:05:31	2025-11-27 02:05:31	f
168	38	4	multiple_choice	Quel est le plus grand désert du monde par sa superficie ?	["Le d\\u00e9sert de l'Antarctique","Le d\\u00e9sert du Sahara","Le d\\u00e9sert de Gobi","Le d\\u00e9sert de Kalahari"]	[0]	\N	2025-11-27 02:05:32	2025-11-27 02:05:32	f
169	38	5	multiple_choice	Quelle est la plus grande île du monde par superficie ?	["Groenland","Madagascar","Nouvelle-Guin\\u00e9e","Borneo"]	[0]	\N	2025-11-27 02:05:33	2025-11-27 02:05:33	f
170	38	6	multiple_choice	Quel est le plus grand désert du monde par sa superficie ?	["Le d\\u00e9sert du Sahara","Le d\\u00e9sert de Gobi","Le d\\u00e9sert d'Atacama","Le d\\u00e9sert de Mojave"]	[0]	\N	2025-11-27 02:05:34	2025-11-27 02:05:34	f
171	38	7	true_false	Le Nil est le plus long fleuve du monde.	["Vrai","Faux"]	[0]	\N	2025-11-27 02:05:35	2025-11-27 02:05:35	f
172	38	8	multiple_choice	Quelle est la plus grande île du monde par superficie ?	["Groenland","Madagascar","Australie","Islande"]	[0]	\N	2025-11-27 02:05:36	2025-11-27 02:05:36	f
173	38	9	multiple_choice	Quelle est la plus grande île du monde en superficie ?	["Groenland","Madagascar","Australie","Borneo"]	[0]	\N	2025-11-27 02:05:37	2025-11-27 02:05:37	f
174	38	10	true_false	La Russie est le plus grand pays du monde par sa superficie.	["Vrai","Faux"]	[0]	\N	2025-11-27 02:05:38	2025-11-27 02:05:38	f
175	38	11	true_false	Le Nil est le fleuve le plus long du monde.	["Vrai","Faux"]	[0]	\N	2025-11-27 02:05:39	2025-11-27 02:05:39	f
176	38	12	true_false	La capitale de l'Australie est Sydney.	["Vrai","Faux"]	[1]	\N	2025-11-27 02:05:39	2025-11-27 02:05:39	f
177	38	13	multiple_choice	Quel est le plus grand désert du monde en superficie ?	["Le d\\u00e9sert de l'Antarctique","Le d\\u00e9sert du Sahara","Le d\\u00e9sert de Gobi","Le d\\u00e9sert du Kalahari"]	[0]	\N	2025-11-27 02:05:40	2025-11-27 02:05:40	f
178	38	14	true_false	Le Nil est le plus long fleuve du monde.	["Vrai","Faux"]	[0]	\N	2025-11-27 02:05:43	2025-11-27 02:05:43	f
179	38	15	true_false	Le Nil est le plus long fleuve du monde.	["Vrai","Faux"]	[0]	\N	2025-11-27 02:05:43	2025-11-27 02:05:43	f
180	38	16	multiple_choice	Quel est le plus grand désert du monde en superficie ?	["Le d\\u00e9sert de l'Antarctique","Le d\\u00e9sert du Sahara","Le d\\u00e9sert de Gobi","Le d\\u00e9sert du Kalahari"]	[0]	\N	2025-11-27 02:05:44	2025-11-27 02:05:44	f
181	38	17	multiple_choice	Quelle est la capitale du Canada ?	["Ottawa","Toronto","Vancouver","Montr\\u00e9al"]	[0]	\N	2025-11-27 02:05:46	2025-11-27 02:05:46	f
182	38	18	true_false	La France est le plus grand pays de l'Union européenne en superficie.	["Vrai","Faux"]	[0]	\N	2025-11-27 02:05:47	2025-11-27 02:05:47	f
183	38	19	multiple_choice	Quel est le plus grand désert du monde par superficie ?	["Le d\\u00e9sert Antarctique","Le d\\u00e9sert du Sahara","Le d\\u00e9sert de Gobi","Le d\\u00e9sert de Kalahari"]	[0]	\N	2025-11-27 02:05:48	2025-11-27 02:05:48	f
184	38	20	multiple_choice	Quel est le plus grand désert du monde par sa superficie ?	["Le d\\u00e9sert de l'Antarctique","Le d\\u00e9sert du Sahara","Le d\\u00e9sert de Gobi","Le d\\u00e9sert d'Atacama"]	[0]	\N	2025-11-27 02:05:49	2025-11-27 02:05:49	f
185	39	1	multiple_choice	Quel est le plus grand désert du monde par sa superficie ?	["Le d\\u00e9sert de l'Antarctique","Le d\\u00e9sert du Sahara","Le d\\u00e9sert de Gobi","Le d\\u00e9sert du Kalahari"]	[0]	\N	2025-11-27 02:18:18	2025-11-27 02:18:18	f
186	39	2	multiple_choice	Quel est le plus grand désert du monde ?	["Le d\\u00e9sert de l'Antarctique","Le d\\u00e9sert du Sahara","Le d\\u00e9sert de Gobi","Le d\\u00e9sert de Kalahari"]	[0]	\N	2025-11-27 02:18:19	2025-11-27 02:18:19	f
187	39	3	multiple_choice	Quelle est la plus grande île du monde en superficie ?	["Groenland","Madagascar","Born\\u00e9o","Nouvelle-Guin\\u00e9e"]	[0]	\N	2025-11-27 02:18:20	2025-11-27 02:18:20	f
188	39	4	multiple_choice	Quel est le plus grand désert du monde par superficie ?	["Le d\\u00e9sert de l'Antarctique","Le d\\u00e9sert du Sahara","Le d\\u00e9sert de Gobi","Le d\\u00e9sert des Mojaves"]	[0]	\N	2025-11-27 02:18:21	2025-11-27 02:18:21	f
189	39	5	multiple_choice	Quel est le plus grand désert du monde par sa superficie ?	["Le d\\u00e9sert de Sahara","Le d\\u00e9sert de Gobi","Le d\\u00e9sert d'Atacama","Le d\\u00e9sert de Kalahari"]	[0]	\N	2025-11-27 02:18:23	2025-11-27 02:18:23	f
190	39	6	multiple_choice	Quelle est la plus grande rivière du monde par la longueur totale ?	["Le Nil","L'Amazone","Le Yangts\\u00e9","Le Mississippi"]	[0]	\N	2025-11-27 02:18:23	2025-11-27 02:18:23	f
191	39	7	multiple_choice	Quel est le plus grand désert du monde par sa superficie ?	["Le d\\u00e9sert de l'Antarctique","Le Sahara","Le d\\u00e9sert de Gobi","Le d\\u00e9sert du Kalahari"]	[0]	\N	2025-11-27 02:18:25	2025-11-27 02:18:25	f
192	39	8	multiple_choice	Quel est le plus grand désert du monde par surface ?	["Le d\\u00e9sert de l'Antarctique","Le d\\u00e9sert du Sahara","Le d\\u00e9sert de Gobi","Le d\\u00e9sert du Kalahari"]	[0]	\N	2025-11-27 02:18:26	2025-11-27 02:18:26	f
193	39	9	multiple_choice	Quel est le plus grand désert du monde en termes de superficie ?	["Le d\\u00e9sert de l'Antarctique","Le Sahara","Le d\\u00e9sert de Gobi","Le d\\u00e9sert de Kalahari"]	[0]	\N	2025-11-27 02:18:27	2025-11-27 02:18:27	f
194	39	10	multiple_choice	Quelle est la capitale du Canada ?	["Ottawa","Toronto","Vancouver","Montr\\u00e9al"]	[0]	\N	2025-11-27 02:18:28	2025-11-27 02:18:28	f
195	39	11	multiple_choice	Quelle est la plus grande île du monde ?	["Groenland","Madagascar","Born\\u00e9o","Islande"]	[0]	\N	2025-11-27 02:18:29	2025-11-27 02:18:29	f
196	39	12	multiple_choice	Quelle est la plus grande île du monde en superficie?	["Groenland","Madagascar","Australie","Islande"]	[0]	\N	2025-11-27 02:18:30	2025-11-27 02:18:30	f
197	39	13	multiple_choice	Quelle est la plus grande île du monde par superficie ?	["Groenland","Madagascar","Australie","Born\\u00e9o"]	[0]	\N	2025-11-27 02:18:31	2025-11-27 02:18:31	f
198	39	14	multiple_choice	Quelle est la capitale de l'Australie ?	["Canberra","Sydney","Melbourne","Brisbane"]	[0]	\N	2025-11-27 02:18:31	2025-11-27 02:18:31	f
199	39	15	multiple_choice	Quelle est la plus grande île du monde par superficie ?	["Groenland","Madagascar","Australie","Nouvelle-Guin\\u00e9e"]	[0]	\N	2025-11-27 02:18:32	2025-11-27 02:18:32	f
200	39	16	multiple_choice	Quelle est la plus grande île du monde par superficie ?	["Groenland","Australie","Madagascar","Islande"]	[0]	\N	2025-11-27 02:18:33	2025-11-27 02:18:33	f
201	39	17	multiple_choice	Quel est le plus grand désert du monde ?	["Le d\\u00e9sert de l'Antarctique","Le d\\u00e9sert du Sahara","Le d\\u00e9sert de Gobi","Le d\\u00e9sert du Kalahari"]	[0]	\N	2025-11-27 02:18:34	2025-11-27 02:18:34	f
202	39	18	multiple_choice	Quel est le plus grand désert du monde par superficie ?	["Le d\\u00e9sert Antarctique","Le d\\u00e9sert du Sahara","Le d\\u00e9sert de Gobi","Le d\\u00e9sert d'Atacama"]	[0]	\N	2025-11-27 02:18:35	2025-11-27 02:18:35	f
203	39	19	multiple_choice	Quelle est la plus grande île du monde en superficie ?	["Groenland","Madagascar","Australie","Borneo"]	[0]	\N	2025-11-27 02:18:36	2025-11-27 02:18:36	f
204	39	20	multiple_choice	Quel est le plus grand désert du monde ?	["Le d\\u00e9sert de l'Antarctique","Le d\\u00e9sert du Sahara","Le d\\u00e9sert de Gobi","Le d\\u00e9sert de Kalahari"]	[0]	\N	2025-11-27 02:18:37	2025-11-27 02:18:37	f
205	39	21	multiple_choice	Quelle est la capitale la plus au nord parmi ces villes ?	["Reykjavik, Islande","Oslo, Norv\\u00e8ge","Helsinki, Finlande","Stockholm, Su\\u00e8de"]	[0]	\N	2025-11-27 02:18:38	2025-11-27 02:18:38	t
206	40	1	multiple_choice	Quelle est la protéine prion responsable de la transmission de la tremblante du mouton et de la maladie de Creutzfeldt-Jakob chez l'humain?	["PrPSc","Amylo\\u00efde-b\\u00eata","Tau","Alpha-synucl\\u00e9ine"]	[0]	\N	2025-12-30 19:19:08	2025-12-30 19:19:08	f
207	40	2	true_false	Le ruthénium, un métal de transition, est principalement utilisé comme catalyseur dans des réactions chimiques et est rarement trouvé à l'état pur dans la nature.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:19:09	2025-12-30 19:19:09	f
208	40	3	multiple_choice	Quel physicien a théorisé l'existence des ondes de spin, des perturbations magnétiques se propageant dans les matériaux ferromagnétiques, bien avant leur observation expérimentale?	["Felix Bloch","Werner Heisenberg","Paul Dirac","Wolfgang Pauli"]	[0]	\N	2025-12-30 19:19:10	2025-12-30 19:19:10	f
209	40	4	multiple_choice	Quel est le rôle principal des aquaporines découvertes par Peter Agre, prix Nobel de chimie 2003, dans les membranes cellulaires?	["Faciliter le transport de l'eau \\u00e0 travers la membrane","Catalyser la production d'ATP","Assurer la rigidit\\u00e9 de la membrane cellulaire","Transporter les ions sodium et potassium"]	[0]	\N	2025-12-30 19:19:11	2025-12-30 19:19:11	f
210	40	5	multiple_choice	Quel mathématicien a prouvé l'existence de nombres transcendants, dépassant ainsi la simple résolution d'équations algébriques?	["Joseph Liouville","\\u00c9variste Galois","Bernhard Riemann","David Hilbert"]	[0]	\N	2025-12-30 19:19:12	2025-12-30 19:19:12	f
211	40	6	multiple_choice	Quelle observation cruciale, réalisée par Rosalind Franklin et Maurice Wilkins grâce à la diffraction des rayons X, a permis de révéler la structure hélicoïdale de l'ADN, bien que leur rôle ait été initialement sous-estimé?	["Le motif de diffraction en forme de 'X' sugg\\u00e9rant une structure h\\u00e9lico\\u00efdale r\\u00e9guli\\u00e8re.","La pr\\u00e9sence de bases azot\\u00e9es puriques et pyrimidiques en quantit\\u00e9s \\u00e9gales.","La d\\u00e9couverte de liaisons hydrog\\u00e8ne entre les paires de bases.","L'identification du groupement phosphate comme \\u00e9l\\u00e9ment central de la structure."]	[0]	\N	2025-12-30 19:19:14	2025-12-30 19:19:14	f
212	40	7	true_false	En programmation orientée objet, le polymorphisme permet à un objet de prendre plusieurs formes, mais uniquement si ces formes sont définies dans la même classe.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:19:14	2025-12-30 19:19:14	f
213	40	8	multiple_choice	Quelle est la fonction principale des séquences d'ADN appelées 'introns' dans le processus d'expression génétique?	["\\u00catre \\u00e9limin\\u00e9es de l'ARN messager mature par \\u00e9pissage","Coder directement pour des prot\\u00e9ines fonctionnelles","Servir de point de d\\u00e9part pour la transcription","R\\u00e9guler la stabilit\\u00e9 de l'ADN g\\u00e9nomique"]	[0]	\N	2025-12-30 19:19:16	2025-12-30 19:19:16	f
214	40	9	true_false	Un réseau neuronal convolutif (CNN) est intrinsèquement invariant à la translation, ce qui signifie qu'il peut reconnaître un motif, peu importe où il se trouve dans l'image.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:19:16	2025-12-30 19:19:16	f
215	40	10	true_false	L'exploitation des hydrates de méthane, piégés dans le pergélisol et les sédiments marins, est actuellement une source d'énergie rentable et largement utilisée à l'échelle mondiale.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:19:17	2025-12-30 19:19:17	f
216	40	11	multiple_choice	Quel lauréat du prix Nobel de physiologie ou médecine a vu son prix retiré après des accusations de fraude scientifique, une situation sans précédent dans l'histoire du prix?	["Bengt Samuelsson","David Baltimore","Arvid Carlsson","Yoshinori Ohsumi"]	[0]	\N	2025-12-30 19:19:19	2025-12-30 19:19:19	t
217	41	1	true_false	L'énergie géothermique, exploitée pour le chauffage et l'électricité, est principalement issue de la désintégration d'isotopes radioactifs présents dans le manteau terrestre.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:19:20	2025-12-30 19:19:20	f
258	44	9	multiple_choice	Quelle technologie innovante, développée en Islande, utilise la chaleur géothermique pour transformer le dioxyde de carbone en roche, offrant une solution durable pour le stockage du carbone?	["Carbfix","Climeworks","Project Vesta","Global Thermostat"]	[0]	\N	2025-12-30 19:20:05	2025-12-30 19:20:05	f
218	41	2	multiple_choice	Quelle technologie moderne a permis de cartographier la quasi-totalité du génome humain, ouvrant la voie à des avancées majeures en médecine personnalisée et en thérapie génique?	["Le s\\u00e9quen\\u00e7age \\u00e0 haut d\\u00e9bit (NGS)","La microscopie \\u00e9lectronique \\u00e0 transmission (MET)","La r\\u00e9sonance magn\\u00e9tique nucl\\u00e9aire (RMN)","La chromatographie en phase gazeuse coupl\\u00e9e \\u00e0 la spectrom\\u00e9trie de masse (GC-MS)"]	[0]	\N	2025-12-30 19:19:21	2025-12-30 19:19:21	f
219	41	3	multiple_choice	Quel phénomène physique, observé pour la première fois en 1995, démontre l'existence d'un nouvel état de la matière où des atomes refroidis à des températures proches du zéro absolu se comportent comme une seule entité quantique?	["Condensat de Bose-Einstein","Effet Seebeck","R\\u00e9sonance magn\\u00e9tique nucl\\u00e9aire","Supraconductivit\\u00e9 \\u00e0 haute temp\\u00e9rature"]	[0]	\N	2025-12-30 19:19:22	2025-12-30 19:19:22	f
220	41	4	true_false	Le chronomètre de marine, inventé par John Harrison au XVIIIe siècle, était initialement conçu pour déterminer la longitude en mer, mais son impact s'est limité au domaine de la navigation astronomique.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:19:23	2025-12-30 19:19:23	f
221	41	5	multiple_choice	Quel mathématicien a prouvé l'existence de nombres transcendants, nombres réels non racines d'un polynôme non nul à coefficients entiers, en utilisant une méthode probabiliste?	["Joseph Liouville","Georg Cantor","Alan Turing","Kurt G\\u00f6del"]	[0]	\N	2025-12-30 19:19:24	2025-12-30 19:19:24	f
222	41	6	true_false	Le prix Nobel de chimie 1911 a été attribué à Marie Curie uniquement pour sa découverte des éléments radium et polonium.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:19:25	2025-12-30 19:19:25	f
223	41	7	multiple_choice	Quel langage de programmation, initialement conçu pour l'enseignement, est devenu un pilier du développement de logiciels embarqués et de systèmes d'exploitation grâce à sa portabilité et son contrôle bas niveau?	["C","Python","Java","Pascal"]	[0]	\N	2025-12-30 19:19:26	2025-12-30 19:19:26	f
224	41	8	true_false	La reforestation avec des espèces non indigènes, même si elle augmente la biomasse, contribue toujours positivement à la biodiversité locale.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:19:27	2025-12-30 19:19:27	f
225	41	9	multiple_choice	Quelle est la particularité du 'baiser de la mort' utilisé par certaines espèces de serpents venimeux, comme le mamba noir, en termes d'administration du venin?	["Il d\\u00e9livre une dose massive de venin en une seule morsure rapide et pr\\u00e9cise.","Il paralyse temporairement la victime avant l'injection du venin.","Il injecte un venin \\u00e0 action lente qui se manifeste plusieurs heures apr\\u00e8s la morsure.","Il n'injecte pas de venin, mais provoque un choc anaphylactique par simple contact."]	[0]	\N	2025-12-30 19:19:28	2025-12-30 19:19:28	f
226	41	10	multiple_choice	Quelle est la principale caractéristique de l'étoile de Teegarden, découverte en 2003, qui la rend particulièrement intéressante pour la recherche de vie extraterrestre?	["Elle poss\\u00e8de un syst\\u00e8me plan\\u00e9taire potentiellement habitable tr\\u00e8s proche du n\\u00f4tre.","Elle est la plus grande \\u00e9toile connue \\u00e0 ce jour.","Elle \\u00e9met des ondes radio artificielles d\\u00e9tectables.","Elle est situ\\u00e9e au centre de notre galaxie."]	[0]	\N	2025-12-30 19:19:30	2025-12-30 19:19:30	f
227	41	11	multiple_choice	Quel explorateur, initialement mandaté pour trouver un passage maritime au nord-ouest, a finalement cartographié une grande partie de la côte arctique canadienne, établissant ainsi une base pour les revendications territoriales du Canada dans l'Arctique?	["Roald Amundsen","John Franklin","Henry Hudson","Vitus B\\u00e9ring"]	[0]	\N	2025-12-30 19:19:31	2025-12-30 19:19:31	t
228	42	1	true_false	Le prix Nobel de chimie 1911 a été attribué à Marie Curie uniquement pour sa découverte du radium et du polonium.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:19:32	2025-12-30 19:19:32	f
229	42	2	true_false	L'acidification des océans, principalement due à l'absorption accrue de CO2 atmosphérique, affecte de manière uniforme la capacité de tous les organismes marins à construire et maintenir leurs coquilles et squelettes calcaires.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:19:34	2025-12-30 19:19:34	f
230	42	3	true_false	Chez les mammifères, l'épissage alternatif de l'ARN messager permet à un seul gène de coder pour plusieurs protéines différentes, mais ce processus n'a lieu que dans les cellules somatiques et jamais dans les cellules germinales.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:19:35	2025-12-30 19:19:35	f
231	42	4	multiple_choice	Quel est le nom de la plus grande structure connue dans l'univers observable, un immense groupe de quasars?	["Grand Attracteur","Grand Mur d'Hercule-Couronne bor\\u00e9ale","Superamas de Shapley","Vide du Bouvier"]	[0]	\N	2025-12-30 19:19:36	2025-12-30 19:19:36	f
232	42	5	multiple_choice	Quelle est la principale fonction de la protéine Hedgehog (Hh) dans le développement embryonnaire, une fois sécrétée et liée à son récepteur Patched (Ptc)?	["Induire la diff\\u00e9renciation cellulaire et la morphogen\\u00e8se des tissus","Inhiber la prolif\\u00e9ration cellulaire et induire l'apoptose","Activer la r\\u00e9ponse immunitaire inn\\u00e9e contre les agents pathog\\u00e8nes","R\\u00e9guler le m\\u00e9tabolisme \\u00e9nerg\\u00e9tique et la production d'ATP"]	[0]	\N	2025-12-30 19:19:37	2025-12-30 19:19:37	f
233	42	6	true_false	La "singularité technologique", un concept souvent associé à l'intelligence artificielle, désigne un point hypothétique dans le futur où la croissance technologique devient incontrôlable et irréversible, entraînant des changements imprévisibles pour la civilisation humaine, mais ce concept est universellement accepté et considéré comme inévitable par la communauté scientifique.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:19:38	2025-12-30 19:19:38	f
234	42	7	true_false	L'effet Casimir, une force attractive entre deux plaques conductrices parallèles dans le vide, est une conséquence directe des fluctuations quantiques du champ électromagnétique, mais son existence n'a jamais été expérimentalement vérifiée avec une précision suffisante pour être considérée comme une preuve définitive de la théorie quantique des champs.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:19:39	2025-12-30 19:19:39	f
235	42	8	multiple_choice	Quel élément chimique, découvert en 1803, tire son nom d'un astéroïde découvert deux ans auparavant, et est utilisé dans certains alliages pour augmenter leur résistance à la corrosion?	["C\\u00e9rium","Niobium","Tantale","Ruth\\u00e9nium"]	[0]	\N	2025-12-30 19:19:40	2025-12-30 19:19:40	f
236	42	9	true_false	La conjecture de Syracuse, aussi connue sous le nom de conjecture de Collatz, a été prouvée par Terence Tao en 2019, résolvant ainsi un problème mathématique resté ouvert pendant plus de 80 ans.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:19:41	2025-12-30 19:19:41	f
237	42	10	true_false	La technologie moderne d'impression 3D permet désormais de créer des organes humains fonctionnels complets, prêts à être transplantés, éliminant ainsi les problèmes de pénurie de donneurs et de rejet immunitaire.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:19:42	2025-12-30 19:19:42	f
238	42	11	multiple_choice	Quelle observation cruciale, réalisée par Rosalind Franklin et Maurice Wilkins, a permis de confirmer la structure hélicoïdale de l'ADN, mais a été présentée par James Watson et Francis Crick sans attribution complète, menant à une controverse éthique persistante?	["La diffraction des rayons X montrant une structure en h\\u00e9lice","L'analyse des bases azot\\u00e9es r\\u00e9v\\u00e9lant des paires compl\\u00e9mentaires","La centrifugation diff\\u00e9rentielle isolant l'ADN pur","La microscopie \\u00e9lectronique visualisant les brins d'ADN"]	[0]	\N	2025-12-30 19:19:43	2025-12-30 19:19:43	t
239	43	1	multiple_choice	Quelle technologie moderne a permis de cartographier le génome humain complet pour la première fois?	["Le s\\u00e9quen\\u00e7age \\u00e0 haut d\\u00e9bit","La microscopie \\u00e9lectronique \\u00e0 balayage","La chromatographie en phase gazeuse","La spectroscopie d'absorption atomique"]	[0]	\N	2025-12-30 19:19:45	2025-12-30 19:19:45	f
240	43	2	true_false	L'épigénétique réfère aux changements dans l'expression des gènes qui n'impliquent pas de modifications de la séquence d'ADN elle-même.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:19:46	2025-12-30 19:19:46	f
241	43	3	true_false	La conjecture de Syracuse, proposée par Lothar Collatz, a été prouvée en 2019 par Terence Tao.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:19:47	2025-12-30 19:19:47	f
242	43	4	true_false	L'albédo d'une surface terrestre recouverte de glace diminue à mesure que la température globale augmente, accélérant ainsi le réchauffement climatique.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:19:48	2025-12-30 19:19:48	f
243	43	5	multiple_choice	Quelle est la première utilisation documentée de la thérapie par bactériophages pour traiter une infection humaine, et dans quel contexte a-t-elle eu lieu?	["Traitement d'une dysenterie en France en 1919","Traitement d'une pneumonie en Russie en 1932","Traitement d'une septic\\u00e9mie en Allemagne en 1945","Traitement d'une infection urinaire aux \\u00c9tats-Unis en 1950"]	[0]	\N	2025-12-30 19:19:49	2025-12-30 19:19:49	f
244	43	6	true_false	Un réseau neuronal convolutif (CNN) est intrinsèquement invariant à la rotation des images sans nécessiter d'ajustements spécifiques.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:19:50	2025-12-30 19:19:50	f
245	43	7	multiple_choice	Quelle innovation, introduite en Islande dans les années 1970, a permis d'exploiter l'énergie géothermique de manière plus efficace et durable en réduisant les émissions de sulfure d'hydrogène?	["Le syst\\u00e8me de collecte et de r\\u00e9injection des fluides g\\u00e9othermiques","L'utilisation de turbines \\u00e0 cycle combin\\u00e9","L'installation de filtres \\u00e0 charbon actif","La construction de chemin\\u00e9es de dispersion \\u00e0 haute altitude"]	[0]	\N	2025-12-30 19:19:51	2025-12-30 19:19:51	f
246	43	8	true_false	Le langage de programmation 'Brainfuck' est Turing-complet malgré son ensemble d'instructions minimaliste composé de seulement huit commandes.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:19:52	2025-12-30 19:19:52	f
247	43	9	true_false	Chez certaines espèces de poissons téléostéens, la détermination du sexe est influencée par la température de l'eau pendant le développement embryonnaire, un phénomène appelé 'détermination sexuelle dépendante de la température'.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:19:53	2025-12-30 19:19:53	f
248	43	10	multiple_choice	Quel prix Nobel a été attribué à Dorothy Hodgkin en 1964, reconnaissant son travail pionnier dans la détermination des structures de molécules biochimiques importantes par cristallographie aux rayons X?	["Chimie","Physique","Physiologie ou M\\u00e9decine","Litt\\u00e9rature"]	[0]	\N	2025-12-30 19:19:54	2025-12-30 19:19:54	f
249	43	11	multiple_choice	Quelle est la principale raison pour laquelle les télescopes spatiaux utilisant l'interférométrie à longue base (VLBI) rencontrent des difficultés significatives pour observer les ondes radio de très basse fréquence (VLF) provenant de l'espace lointain?	["L'ionosph\\u00e8re terrestre bloque efficacement les ondes VLF, rendant leur d\\u00e9tection depuis l'espace complexe.","Les interf\\u00e9rences \\u00e9lectromagn\\u00e9tiques g\\u00e9n\\u00e9r\\u00e9es par les satellites de communication masquent les signaux VLF faibles.","La faible r\\u00e9solution angulaire des t\\u00e9lescopes spatiaux VLBI rend difficile la distinction des sources VLF.","Les ondes VLF sont absorb\\u00e9es par la poussi\\u00e8re interstellaires, limitant leur port\\u00e9e observable."]	[0]	\N	2025-12-30 19:19:55	2025-12-30 19:19:55	t
250	44	1	true_false	Le ruthénium, un métal de transition rare, est utilisé comme catalyseur dans certaines réactions chimiques et est toujours radioactif.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:19:57	2025-12-30 19:19:57	f
251	44	2	multiple_choice	Quel satellite naturel de Saturne possède une atmosphère dense et des mers d'hydrocarbures liquides, le rendant unique dans le système solaire?	["Titan","Encelade","Rh\\u00e9a","Japet"]	[0]	\N	2025-12-30 19:19:58	2025-12-30 19:19:58	f
252	44	3	true_false	La bioluminescence chez les champignons est toujours due à la présence de luciférase, une enzyme oxydant la luciférine.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:19:59	2025-12-30 19:19:59	f
253	44	4	true_false	Le "rayon de la mort" prétendument inventé par Nikola Tesla dans les années 1930, capable de détruire des avions à distance, a été prouvé fonctionnel et a été utilisé par plusieurs armées pendant la Seconde Guerre mondiale.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:20:00	2025-12-30 19:20:00	f
254	44	5	multiple_choice	Quelle initiative écologique, lancée dans les années 1970 par Wangari Maathai au Kenya, a permis de planter des millions d'arbres pour lutter contre la déforestation et améliorer les conditions de vie des communautés locales?	["Le Mouvement Ceinture Verte","L'Alliance pour la Terre","Le Projet Eden","L'Op\\u00e9ration Mains Vertes"]	[0]	\N	2025-12-30 19:20:01	2025-12-30 19:20:01	f
255	44	6	multiple_choice	Quelle observation cruciale, réalisée par Rosalind Franklin, a été déterminante pour la compréhension de la structure de l'ADN, bien que son rôle ait été initialement sous-estimé?	["La diffraction des rayons X montrant la structure h\\u00e9lico\\u00efdale de l'ADN","La d\\u00e9couverte des ribosomes","L'identification des groupes sanguins","La mise en \\u00e9vidence de la photosynth\\u00e8se"]	[0]	\N	2025-12-30 19:20:02	2025-12-30 19:20:02	f
256	44	7	true_false	L'effet Magnus, qui explique la trajectoire incurvée d'une balle de golf en rotation, est principalement dû à la viscosité de l'air plutôt qu'à la différence de pression entre les côtés de la balle.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:20:03	2025-12-30 19:20:03	f
257	44	8	multiple_choice	Quel est le rôle des histones dans l'organisation du matériel génétique au sein du noyau cellulaire?	["Condenser et organiser l'ADN en nucl\\u00e9osomes","R\\u00e9pliquer l'ADN lors de la division cellulaire","Traduire l'ARN messager en prot\\u00e9ines","Transporter l'ARN hors du noyau"]	[0]	\N	2025-12-30 19:20:04	2025-12-30 19:20:04	f
259	44	10	true_false	La découverte de l'insuline par Frederick Banting et Charles Best en 1921 a immédiatement éradiqué le diabète de type 1, rendant les injections quotidiennes inutiles dès l'année suivante.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:20:06	2025-12-30 19:20:06	f
260	44	11	multiple_choice	Quelle avancée technologique a permis la création des premiers ordinateurs quantiques topologiques, promettant une stabilité accrue des qubits par rapport aux systèmes traditionnels?	["L'utilisation d'anyons non-ab\\u00e9liens comme qubits","Le d\\u00e9veloppement de supraconducteurs \\u00e0 haute temp\\u00e9rature critique","La miniaturisation extr\\u00eame des transistors CMOS","L'impl\\u00e9mentation de r\\u00e9seaux de neurones r\\u00e9currents"]	[0]	\N	2025-12-30 19:20:07	2025-12-30 19:20:07	t
261	46	1	multiple_choice	Quel lac d'Asie centrale est connu pour sa salinité fluctuante et son assèchement progressif dû à des détournements d'eau pour l'irrigation?	["La mer d'Aral","Le lac Ba\\u00efkal","Le lac Balkhash","Le lac Issyk-Koul"]	[0]	\N	2025-12-30 19:38:00	2025-12-30 19:38:00	f
262	46	2	true_false	Le fleuve Okavango, qui se jette dans un delta intérieur au Botswana, est le seul fleuve au monde à ne pas avoir d'embouchure maritime.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:38:01	2025-12-30 19:38:01	f
263	46	3	multiple_choice	Quelle forêt, située en République démocratique du Congo, abrite une concentration exceptionnelle d'okapis, une espèce de girafe unique, et est menacée par l'exploitation minière illégale?	["La for\\u00eat d'Ituri","La for\\u00eat de Bialowieza","La for\\u00eat de Daintree","La for\\u00eat de Bavi\\u00e8re"]	[0]	\N	2025-12-30 19:38:02	2025-12-30 19:38:02	f
264	46	4	true_false	Le drapeau du Bhoutan représente un dragon blanc, symbole de la pureté et de la loyauté, tenant des joyaux représentant la richesse et la perfection.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:38:03	2025-12-30 19:38:03	f
265	46	5	true_false	La mer de Ross, située dans l'océan Austral, est la seule mer au monde où la pêche est totalement interdite afin de préserver son écosystème unique.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:38:04	2025-12-30 19:38:04	f
266	46	6	multiple_choice	Quel volcan indonésien, tristement célèbre pour son éruption cataclysmique de 1883, a vu émerger un nouveau cône volcanique appelé Anak Krakatau, signifiant 'enfant de Krakatau'?	["Krakatoa","Mont Tambora","Mont Merapi","Mont Bromo"]	[0]	\N	2025-12-30 19:38:05	2025-12-30 19:38:05	f
267	46	7	multiple_choice	Quel est le plus long système de grottes sous-marines exploré à ce jour, connu pour ses cénotes et sa biodiversité unique?	["Sistema Sac Actun (Mexique)","Grottes de Waitomo (Nouvelle-Z\\u00e9lande)","Grottes de \\u0160kocjan (Slov\\u00e9nie)","Grotte de Son Doong (Vietnam)"]	[0]	\N	2025-12-30 19:38:06	2025-12-30 19:38:06	f
268	46	8	multiple_choice	Quel parc national, situé en Tanzanie, est célèbre pour la 'Grande Migration' annuelle de millions de gnous, zèbres et gazelles?	["Serengeti","Yellowstone","Banff","Kruger"]	[0]	\N	2025-12-30 19:38:07	2025-12-30 19:38:07	f
269	46	9	multiple_choice	Quelle initiative environnementale, lancée en 2007 par Wangari Maathai, vise à planter des milliards d'arbres à travers le monde pour lutter contre la déforestation et la dégradation des sols?	["La Campagne des Milliards d'Arbres","Le Projet Grande Muraille Verte","L'Initiative de Restauration des Paysages Forestiers","Le D\\u00e9fi de Bonn"]	[0]	\N	2025-12-30 19:38:08	2025-12-30 19:38:08	f
270	46	10	true_false	L'île de Pâques, célèbre pour ses statues Moaï, appartient administrativement à la Polynésie française.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:38:09	2025-12-30 19:38:09	f
271	46	11	multiple_choice	Quel désert est caractérisé par la présence de 'fairy circles', des zones circulaires dépourvues de végétation dont l'origine exacte reste un mystère pour les scientifiques?	["Le d\\u00e9sert du Namib","Le d\\u00e9sert d'Atacama","Le d\\u00e9sert de Gobi","Le d\\u00e9sert du Sahara"]	[0]	\N	2025-12-30 19:38:10	2025-12-30 19:38:10	t
272	47	1	true_false	L'île de Pâques, célèbre pour ses statues Moaï, appartient administrativement à la Polynésie française.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:38:11	2025-12-30 19:38:11	f
273	47	2	true_false	Le mont Tambora, dont l'éruption en 1815 a eu des conséquences climatiques mondiales, est situé sur l'île de Sumbawa, en Indonésie.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:38:12	2025-12-30 19:38:12	f
274	47	3	multiple_choice	Quel désert est connu pour ses 'cercles de fées', des zones circulaires de terre stérile entourées de végétation?	["Le d\\u00e9sert du Namib","Le d\\u00e9sert d'Atacama","Le d\\u00e9sert de Gobi","Le d\\u00e9sert du Sahara"]	[0]	\N	2025-12-30 19:38:13	2025-12-30 19:38:13	f
275	47	4	multiple_choice	Quel est le plus long système de grottes sous-marines exploré à ce jour, situé dans la péninsule du Yucatán?	["Sistema Ox Bel Ha","Sistema Dos Ojos","Sistema Sac Actun","Sistema Nohoch Nah Chich"]	[0]	\N	2025-12-30 19:38:14	2025-12-30 19:38:14	f
276	47	5	multiple_choice	Quelle est la principale conséquence environnementale de la fonte du pergélisol arctique, au-delà de l'élévation du niveau de la mer?	["La lib\\u00e9ration massive de m\\u00e9thane, un puissant gaz \\u00e0 effet de serre","L'augmentation de la salinit\\u00e9 des oc\\u00e9ans","La prolif\\u00e9ration d'algues toxiques dans les eaux polaires","La diminution de la biodiversit\\u00e9 marine due \\u00e0 l'acidification"]	[0]	\N	2025-12-30 19:38:15	2025-12-30 19:38:15	f
277	47	6	multiple_choice	Quelle est la particularité de la frontière entre la Belgique et les Pays-Bas à Baarle-Hertog/Baarle-Nassau?	["Elle est constitu\\u00e9e d'une mosa\\u00efque d'enclaves et de contre-enclaves.","Elle est enti\\u00e8rement trac\\u00e9e par des canaux navigables.","Elle est d\\u00e9limit\\u00e9e par une ancienne voie romaine.","Elle est marqu\\u00e9e par une zone d\\u00e9militaris\\u00e9e de 500 m\\u00e8tres de large."]	[0]	\N	2025-12-30 19:38:17	2025-12-30 19:38:17	f
278	47	7	true_false	Le lac Baïkal, en Russie, abrite la seule espèce de phoque d'eau douce au monde.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:38:18	2025-12-30 19:38:18	f
279	47	8	true_false	Le drapeau du Bhoutan représente un dragon blanc, symbole de la 'Drukpa Lineage' du bouddhisme tibétain, tenant des joyaux représentant la richesse et la perfection du pays.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:38:19	2025-12-30 19:38:19	f
280	47	9	true_false	La mer des Sargasses, située dans l'océan Atlantique Nord, est la seule mer au monde qui n'est délimitée par aucune côte.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:38:19	2025-12-30 19:38:19	f
281	47	10	multiple_choice	Quel climat est caractérisé par une forte amplitude thermique annuelle, des étés chauds et des hivers froids, et se trouve principalement dans les régions continentales éloignées des influences maritimes?	["Climat continental","Climat \\u00e9quatorial","Climat m\\u00e9diterran\\u00e9en","Climat polaire"]	[0]	\N	2025-12-30 19:38:20	2025-12-30 19:38:20	f
282	47	11	multiple_choice	Quel parc national abrite la plus grande concentration de glaciers de vallée en dehors des régions polaires?	["Le parc national de Wrangell-St. Elias (Alaska)","Le parc national de Los Glaciares (Argentine)","Le parc national de Jotunheimen (Norv\\u00e8ge)","Le parc national de Fiordland (Nouvelle-Z\\u00e9lande)"]	[0]	\N	2025-12-30 19:38:21	2025-12-30 19:38:21	t
283	48	1	true_false	La mer de Ross, située dans l'océan Austral, est la seule mer au monde où la pêche est totalement interdite, afin de préserver son écosystème unique.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:38:23	2025-12-30 19:38:23	f
284	48	2	true_false	La capitale administrative de la Bolivie est Sucre, tandis que La Paz est la capitale constitutionnelle.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:38:24	2025-12-30 19:38:24	f
285	48	3	true_false	L'île de Pâques, célèbre pour ses statues Moaï, appartient administrativement à la Polynésie française.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:38:25	2025-12-30 19:38:25	f
286	48	4	true_false	Le concept de "dette écologique" fait référence à l'accumulation de dettes financières des pays en développement envers les pays industrialisés en raison des coûts liés à la dégradation environnementale causée par ces derniers.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:38:26	2025-12-30 19:38:26	f
287	48	5	true_false	Le mont Erebus, situé en Antarctique, est le volcan actif le plus austral de la planète et est connu pour son lac de lave phonolitique persistant.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:38:27	2025-12-30 19:38:27	f
288	48	6	multiple_choice	Quel est le plus long réseau de grottes sous-marines exploré à ce jour, connu pour ses cénotes et sa biodiversité unique ?	["Sistema Sac Actun (Mexique)","Grottes de Waitomo (Nouvelle-Z\\u00e9lande)","Grotte de Son Doong (Vietnam)","Eisriesenwelt (Autriche)"]	[0]	\N	2025-12-30 19:38:28	2025-12-30 19:38:28	f
289	48	7	true_false	Le Parc National de la Vanoise, en France, fut le premier parc national créé dans le pays, précédant celui des Écrins de plusieurs années.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:38:29	2025-12-30 19:38:29	f
290	48	8	multiple_choice	Quel fleuve d'Asie du Sud-Est est connu pour son phénomène unique de 'fleuve inversé' durant la saison des pluies, où son débit s'inverse temporairement en raison de la pression des eaux du Mékong ?	["Le Tonl\\u00e9 Sap","L'Irrawaddy","Le Chao Phraya","Le fleuve Rouge"]	[0]	\N	2025-12-30 19:38:30	2025-12-30 19:38:30	f
291	48	9	true_false	Le lac Baïkal, en Russie, contient environ 20% des réserves mondiales d'eau douce de surface non gelée, ce qui en fait le plus grand réservoir d'eau douce liquide au monde.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:38:31	2025-12-30 19:38:31	f
292	48	10	multiple_choice	Quelle chaîne de montagnes abrite le 'Denali', connu comme le point culminant d'Amérique du Nord, et est caractérisée par un climat subarctique rigoureux influençant fortement sa biodiversité ?	["La cha\\u00eene d'Alaska","Les Rocheuses","La Sierra Nevada","Les Appalaches"]	[0]	\N	2025-12-30 19:38:32	2025-12-30 19:38:32	f
293	48	11	multiple_choice	Quel désert est connu pour ses 'fairy circles', des zones circulaires de terre nue entourées d'herbe, dont l'origine exacte reste un mystère scientifique ?	["Le d\\u00e9sert du Namib","Le d\\u00e9sert d'Atacama","Le d\\u00e9sert de Gobi","Le d\\u00e9sert du Sahara"]	[0]	\N	2025-12-30 19:38:33	2025-12-30 19:38:33	t
294	49	1	multiple_choice	Quelle capitale sud-américaine abrite le plus haut téléphérique urbain du monde?	["La Paz","Quito","Bogota","Santiago"]	[0]	\N	2025-12-30 19:38:34	2025-12-30 19:38:34	f
295	49	2	true_false	Le fleuve Zambèze est le seul fleuve d'Afrique australe à se jeter dans l'océan Indien.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:38:35	2025-12-30 19:38:35	f
296	49	3	true_false	La mer des Sargasses, située dans l'océan Atlantique Nord, est la seule mer au monde qui n'est délimitée par aucune côte.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:38:36	2025-12-30 19:38:36	f
297	49	4	multiple_choice	Quel désert est connu pour ses 'chotts', des lacs salés peu profonds et temporaires, formés par l'évaporation de l'eau?	["Le Sahara","Le d\\u00e9sert d'Atacama","Le d\\u00e9sert de Gobi","Le d\\u00e9sert du Kalahari"]	[0]	\N	2025-12-30 19:38:37	2025-12-30 19:38:37	f
298	49	5	multiple_choice	Quel lac d'Asie centrale est connu pour sa salinité variable et son partage entre deux pays, l'un utilisant son eau pour l'industrie et l'autre pour la pêche?	["Le lac Balkhash","Le lac Issyk-Koul","Le lac Ba\\u00efkal","Le lac Khanka"]	[0]	\N	2025-12-30 19:38:38	2025-12-30 19:38:38	f
299	49	6	multiple_choice	Quel volcan indonésien est célèbre pour son lac de cratère turquoise, résultat de fortes concentrations d'acide sulfurique et d'autres minéraux dissous?	["Kawah Ijen","Mont Bromo","Mont Merapi","Mont Agung"]	[0]	\N	2025-12-30 19:38:39	2025-12-30 19:38:39	f
300	49	7	multiple_choice	Quel type de climat est caractérisé par des étés chauds et secs, et des hivers doux et humides, favorisant la culture de l'olivier et de la vigne?	["Climat m\\u00e9diterran\\u00e9en","Climat \\u00e9quatorial","Climat subarctique","Climat d\\u00e9sertique"]	[0]	\N	2025-12-30 19:38:39	2025-12-30 19:38:39	f
301	49	8	true_false	La déforestation en Amazonie a un impact direct sur le cycle de l'eau en diminuant l'évapotranspiration, ce qui peut entraîner une réduction des précipitations dans des régions éloignées comme le Midwest américain.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:38:41	2025-12-30 19:38:41	f
302	49	9	true_false	Le Parc National de la Vanoise, premier parc national français, a été initialement créé dans le but principal de protéger le bouquetin des Alpes, une espèce alors menacée d'extinction.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:38:41	2025-12-30 19:38:41	f
303	49	10	true_false	Le point le plus profond de la fosse des Mariannes, le Challenger Deep, est plus éloigné du niveau de la mer que le mont Everest n'est haut.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:38:42	2025-12-30 19:38:42	f
304	49	11	multiple_choice	Quelle est la principale cause de la formation des 'forêts de nuages' (cloud forests), écosystèmes forestiers rares et fragiles, que l'on retrouve principalement dans les régions montagneuses tropicales et subtropicales?	["La condensation horizontale de l'humidit\\u00e9 atmosph\\u00e9rique due aux vents ascendants forc\\u00e9s par le relief.","L'accumulation de d\\u00e9p\\u00f4ts volcaniques riches en min\\u00e9raux favorisant une croissance v\\u00e9g\\u00e9tale exub\\u00e9rante.","La pr\\u00e9sence de nappes phr\\u00e9atiques peu profondes alimentant constamment la v\\u00e9g\\u00e9tation.","L'absence de saison s\\u00e8che marqu\\u00e9e permettant une photosynth\\u00e8se continue tout au long de l'ann\\u00e9e."]	[0]	\N	2025-12-30 19:38:44	2025-12-30 19:38:44	t
305	51	1	true_false	Le mont Tambora, dont l'éruption en 1815 a causé une année sans été, est situé sur l'île de Sumbawa en Indonésie et est classifié comme un stratovolcan de type explosif.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:38:45	2025-12-30 19:38:45	f
306	51	2	multiple_choice	Quel est le nom du plus long système de grottes sous-marines exploré à ce jour, situé au Mexique?	["Sistema Ox Bel Ha","Sistema Dos Ojos","Great Blue Hole","Cenote Angelita"]	[0]	\N	2025-12-30 19:38:46	2025-12-30 19:38:46	f
307	51	3	multiple_choice	Quelle capitale sud-américaine abrite le 'Museo Larco', célèbre pour sa vaste collection d'art précolombien, notamment des objets en or et en argent?	["Lima","Bogota","Santiago","Caracas"]	[0]	\N	2025-12-30 19:38:47	2025-12-30 19:38:47	f
308	51	4	true_false	Le Parc National de la Vanoise, en France, a été le premier parc national créé dans le pays, devançant celui des Écrins de plus de dix ans.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:38:48	2025-12-30 19:38:48	f
309	51	5	multiple_choice	Quel lac d'Asie centrale est connu pour sa salinité exceptionnellement élevée et ses dépôts de natron, utilisés historiquement dans le processus de momification égyptien?	["Le lac Balkhash","Le lac Ba\\u00efkal","Le lac Issyk-Koul","Le lac Qinghai"]	[0]	\N	2025-12-30 19:38:49	2025-12-30 19:38:49	f
310	51	6	true_false	L'île de Pâques, célèbre pour ses statues Moaï, appartient administrativement à la Polynésie française.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:38:50	2025-12-30 19:38:50	f
311	51	7	true_false	La déforestation en Amazonie a un impact direct sur le cycle de l'eau à l'échelle continentale, influençant les précipitations jusqu'en Argentine.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:38:51	2025-12-30 19:38:51	f
312	51	8	true_false	La région contestée du Haut-Karabakh est internationalement reconnue comme faisant partie du territoire de l'Arménie.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:38:52	2025-12-30 19:38:52	f
313	51	9	true_false	La forêt de Białowieża, partagée entre la Pologne et la Biélorussie, abrite la plus grande population de bisons d'Europe en liberté.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:38:53	2025-12-30 19:38:53	f
314	51	10	true_false	La zone climatique dite "méditerranéenne" est caractérisée par des étés secs et chauds, et des hivers doux et humides, mais elle est également la seule zone climatique où les précipitations sont exclusivement concentrées pendant la saison hivernale.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:38:54	2025-12-30 19:38:54	f
315	51	11	multiple_choice	Quel pays africain a intégré une pioche et un fusil Kalachnikov sur son drapeau national après son indépendance, symbolisant respectivement l'agriculture et la défense ?	["Mozambique","Angola","Zimbabwe","Guin\\u00e9e-Bissau"]	[0]	\N	2025-12-30 19:38:55	2025-12-30 19:38:55	t
316	52	1	multiple_choice	Quel désert abrite le 'Trou Bleu', une doline sous-marine spectaculaire visible depuis l'espace?	["Le d\\u00e9sert du Dahab","Le d\\u00e9sert du N\\u00e9guev","Le d\\u00e9sert du Sahara","Le d\\u00e9sert d'Atacama"]	[0]	\N	2025-12-30 19:40:05	2025-12-30 19:40:05	f
317	52	2	true_false	La cordillère des Andes est la plus longue chaîne de montagnes émergée au monde, surpassant l'Himalaya en longueur.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:40:05	2025-12-30 19:40:05	f
318	52	3	true_false	La frontière entre la Belgique et les Pays-Bas présente une particularité unique : elle traverse littéralement des maisons, divisant certaines propriétés en deux territoires distincts.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:40:06	2025-12-30 19:40:06	f
319	52	4	true_false	Le lac Baïkal, en Sibérie, contient environ 20% des réserves mondiales d'eau douce de surface non gelée.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:40:07	2025-12-30 19:40:07	f
320	52	5	multiple_choice	Quelle mer abrite le 'Great Blue Hole', un cénote sous-marin spectaculaire et site de plongée renommé, situé au large des côtes du Belize?	["Mer des Cara\\u00efbes","Mer M\\u00e9diterran\\u00e9e","Oc\\u00e9an Arctique","Mer de Tasmanie"]	[0]	\N	2025-12-30 19:40:08	2025-12-30 19:40:08	f
321	52	6	multiple_choice	Quel volcan indonésien est célèbre pour ses 'feux bleus' nocturnes, un phénomène dû à la combustion de gaz sulfureux?	["Kawah Ijen","Mont Bromo","Mont Merapi","Mont Agung"]	[0]	\N	2025-12-30 19:40:09	2025-12-30 19:40:09	f
322	52	7	true_false	L'acidification des océans, principalement due à l'absorption accrue de CO2 atmosphérique, affecte de manière uniforme tous les organismes marins, sans distinction d'espèce ou de stade de développement.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:40:10	2025-12-30 19:40:10	f
323	52	8	multiple_choice	Quelle île, autrefois un lazaret pour lépreux, abrite aujourd'hui un observatoire astronomique de renommée mondiale, profitant de son isolement et de son ciel clair?	["L'\\u00eele de La Palma (Canaries)","L'\\u00eele de Spinalonga (Cr\\u00e8te)","L'\\u00eele de Robben (Afrique du Sud)","L'\\u00eele de P\\u00e2ques (Chili)"]	[0]	\N	2025-12-30 19:40:11	2025-12-30 19:40:11	f
324	52	9	multiple_choice	Quel phénomène climatique, influencé par les courants marins et les vents dominants, est responsable de l'aridité extrême du désert d'Atacama, en Amérique du Sud?	["L'ombre pluviom\\u00e9trique due \\u00e0 la cordill\\u00e8re des Andes","La convergence intertropicale","Le ph\\u00e9nom\\u00e8ne El Ni\\u00f1o","L'alb\\u00e9do \\u00e9lev\\u00e9 de la surface d\\u00e9sertique"]	[0]	\N	2025-12-30 19:40:12	2025-12-30 19:40:12	f
325	52	10	true_false	La forêt de Białowieża, partagée entre la Pologne et la Biélorussie, abrite la plus grande population de bisons d'Europe en liberté.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:40:13	2025-12-30 19:40:13	f
326	52	11	multiple_choice	Quel est le plus long système de grottes sous-marines exploré à ce jour, dépassant largement les autres en termes de longueur totale cartographiée?	["Sistema Sac Actun (Mexique)","Grottes de Waitomo (Nouvelle-Z\\u00e9lande)","Grotte de Son Doong (Vietnam)","Eisriesenwelt (Autriche)"]	[0]	\N	2025-12-30 19:40:14	2025-12-30 19:40:14	t
327	53	1	true_false	La mer de Ross, située dans l'océan Austral, est la plus froide et la plus salée de toutes les mers du globe.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:40:16	2025-12-30 19:40:16	f
328	53	2	true_false	La forêt de Białowieża, partagée entre la Pologne et la Biélorussie, abrite la plus grande population de bisons d'Europe en liberté.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:40:16	2025-12-30 19:40:16	f
329	53	3	multiple_choice	Quelle chaîne de montagnes d'Asie est connue pour abriter des formations de 'forêts de pierre' uniques, sculptées par l'érosion karstique?	["Le Shilin","L'Himalaya","Le Tian Shan","L'Alta\\u00ef"]	[0]	\N	2025-12-30 19:40:18	2025-12-30 19:40:18	f
330	53	4	true_false	La capitale administrative de la Bolivie est Sucre, tandis que La Paz est la capitale constitutionnelle.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:40:18	2025-12-30 19:40:18	f
331	53	5	true_false	La zone climatique dite 'méditerranéenne' se caractérise par des étés chauds et secs et des hivers doux et humides, et se trouve exclusivement autour du bassin méditerranéen.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:40:19	2025-12-30 19:40:19	f
332	53	6	multiple_choice	Quel pays a mis en place le premier 'Fonds Carbone' souverain au monde, destiné à financer des projets de réduction des émissions de gaz à effet de serre à l'échelle internationale?	["Le Danemark","La Norv\\u00e8ge","La Su\\u00e8de","L'Allemagne"]	[0]	\N	2025-12-30 19:40:20	2025-12-30 19:40:20	f
333	53	7	multiple_choice	Quel parc national, situé en Tanzanie, est célèbre pour la Grande Migration annuelle de millions de gnous, zèbres et gazelles?	["Serengeti","Yellowstone","Banff","Kruger"]	[0]	\N	2025-12-30 19:40:21	2025-12-30 19:40:21	f
334	53	8	true_false	La frontière entre la Belgique et les Pays-Bas est la seule au monde à être traversée par des habitations, certaines maisons étant situées à cheval sur les deux pays.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:40:22	2025-12-30 19:40:22	f
335	53	9	true_false	Le mont Tambora, dont l'éruption en 1815 a causé une année sans été, est situé sur l'île de Sumatra.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:40:23	2025-12-30 19:40:23	f
336	53	10	multiple_choice	Quelle île, autrefois nommée 'Santa Cruz', abrite une population de dragons de Komodo et fait partie d'un parc national indonésien?	["Rinca","Komodo","Flores","Sumbawa"]	[0]	\N	2025-12-30 19:40:24	2025-12-30 19:40:24	f
337	53	11	multiple_choice	Quel est le plus long système de grottes sous-marines exploré à ce jour, connu pour ses cénotes et sa biodiversité unique?	["Sistema Ox Bel Ha, Mexique","Grottes de Waitomo, Nouvelle-Z\\u00e9lande","Grotte de Son Doong, Vietnam","Eisriesenwelt, Autriche"]	[0]	\N	2025-12-30 19:40:25	2025-12-30 19:40:25	t
338	54	1	multiple_choice	Quel symbole national figure sur le drapeau de l'Albanie, représentant son héritage et son identité?	["Un aigle bic\\u00e9phale noir","Un lion rampant dor\\u00e9","Une \\u00e9toile rouge \\u00e0 cinq branches","Un croissant de lune argent\\u00e9"]	[0]	\N	2025-12-30 19:40:26	2025-12-30 19:40:26	f
339	54	2	multiple_choice	Quel est le nom du canyon sous-marin le plus profond du monde, surpassant même la profondeur du Grand Canyon?	["Le canyon de Monterey","Le canyon du fleuve Congo","Le canyon de Nazar\\u00e9","Le canyon de la Bering"]	[0]	\N	2025-12-30 19:40:27	2025-12-30 19:40:27	f
340	54	3	true_false	La mer de Ross, située dans l'océan Austral, est la plus froide et la plus méridionale des mers bordières de l'Antarctique, et est presque entièrement recouverte de glace toute l'année.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:40:28	2025-12-30 19:40:28	f
341	54	4	true_false	Le mont Erebus, situé en Antarctique, est le volcan actif le plus austral de la planète et est connu pour son lac de lave phonolitique persistant.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:40:29	2025-12-30 19:40:29	f
342	54	5	true_false	La cordillère Blanche, située au Pérou, est entièrement composée de roches sédimentaires.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:40:30	2025-12-30 19:40:30	f
343	54	6	multiple_choice	Quelle zone climatique est caractérisée par une forte amplitude thermique annuelle et des précipitations concentrées en été, favorisant la culture du riz?	["Climat subtropical humide","Climat d\\u00e9sertique chaud","Climat polaire","Climat \\u00e9quatorial"]	[0]	\N	2025-12-30 19:40:31	2025-12-30 19:40:31	f
344	54	7	true_false	La capitale administrative de la Bolivie est Sucre, tandis que La Paz est la capitale constitutionnelle.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:40:31	2025-12-30 19:40:31	f
345	54	8	multiple_choice	Quelle est la principale conséquence environnementale de l'exploitation minière des nodules polymétalliques dans les abysses océaniques?	["Destruction des habitats benthiques profonds et lib\\u00e9ration de s\\u00e9diments toxiques","Augmentation de la biodiversit\\u00e9 marine gr\\u00e2ce \\u00e0 la cr\\u00e9ation de nouveaux substrats","R\\u00e9duction significative de l'acidification des oc\\u00e9ans","Am\\u00e9lioration de la qualit\\u00e9 de l'eau par filtration naturelle"]	[0]	\N	2025-12-30 19:40:33	2025-12-30 19:40:33	f
346	54	9	multiple_choice	Quel lac d'Asie centrale est connu pour sa salinité variable due à son alimentation par deux rivières principales, l'une apportant de l'eau douce et l'autre de l'eau salée?	["Le lac Balkhash","Le lac Ba\\u00efkal","Le lac Issyk-Koul","Le lac Van"]	[0]	\N	2025-12-30 19:40:34	2025-12-30 19:40:34	f
347	54	10	true_false	Le fleuve Amazone est le plus long fleuve du monde, dépassant le Nil en longueur.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:40:35	2025-12-30 19:40:35	f
348	54	11	multiple_choice	Quel est le seul territoire au monde revendiqué par plus de deux pays simultanément, sans qu'aucun n'en exerce un contrôle total et reconnu internationalement?	["Le Triangle de Hala'ib","Le Cachemire","L'Antarctique","L'\\u00cele Hans"]	[0]	\N	2025-12-30 19:40:36	2025-12-30 19:40:36	t
349	56	1	multiple_choice	Quelle île est connue pour abriter le parc national de Komodo, célèbre pour ses varans géants?	["Komodo","Bali","Sumatra","Java"]	[0]	\N	2025-12-30 19:40:37	2025-12-30 19:40:37	f
350	56	2	true_false	Le lac Baïkal, situé en Sibérie, contient à lui seul environ 20% des réserves mondiales d'eau douce de surface non gelée.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:40:38	2025-12-30 19:40:38	f
351	56	3	multiple_choice	Quel parc national abrite la plus grande concentration de pétroglyphes amérindiens du plateau de Columbia?	["Parc national de Petroglyph","Parc national des Arches","Parc national de Mesa Verde","Parc national de Canyonlands"]	[0]	\N	2025-12-30 19:40:39	2025-12-30 19:40:39	f
352	56	4	multiple_choice	Quelle est la principale source de pollution microplastique dans les Grands Lacs d'Amérique du Nord, un problème environnemental majeur?	["Les microbilles issues des produits de soins personnels et des cosm\\u00e9tiques","Les d\\u00e9chets industriels d\\u00e9vers\\u00e9s directement dans les lacs","Les retomb\\u00e9es atmosph\\u00e9riques de la combustion des \\u00e9nergies fossiles","Le ruissellement agricole contenant des pesticides et des engrais"]	[0]	\N	2025-12-30 19:40:40	2025-12-30 19:40:40	f
353	56	5	true_false	Le mont Tambora, dont l'éruption en 1815 a causé une année sans été, est situé sur l'île de Sumbawa, qui fait partie de l'archipel des Moluques.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:40:41	2025-12-30 19:40:41	f
354	56	6	multiple_choice	Quelle chaîne de montagnes abrite le point culminant de l'Afrique australe, le pic Mafadi, dont l'ascension est réputée pour sa difficulté et ses paysages spectaculaires?	["Le Drakensberg","Les monts Outeniqua","Les monts Chimanimani","Le massif du Brandberg"]	[0]	\N	2025-12-30 19:40:42	2025-12-30 19:40:42	f
355	56	7	multiple_choice	Quelle zone climatique est caractérisée par une forte amplitude thermique annuelle, des étés chauds et des hivers froids, et où les précipitations sont modérées et réparties sur l'année, favorisant la croissance des forêts de feuillus?	["Climat continental humide","Climat m\\u00e9diterran\\u00e9en","Climat subtropical humide","Climat polaire"]	[0]	\N	2025-12-30 19:40:44	2025-12-30 19:40:44	f
356	56	8	true_false	La capitale administrative de la Bolivie est Sucre, tandis que La Paz est la capitale constitutionnelle.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:40:44	2025-12-30 19:40:44	f
357	56	9	true_false	Le désert du Karakoum, situé en Asie centrale, est principalement constitué de sable, à l'instar du Sahara.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:40:45	2025-12-30 19:40:45	f
358	56	10	multiple_choice	Quelle mer abrite le point Nemo, également connu comme le pôle d'inaccessibilité maritime, l'endroit le plus éloigné de toute terre émergée sur Terre?	["Oc\\u00e9an Pacifique Sud","Mer de Weddell","Oc\\u00e9an Indien","Mer d'Arabie"]	[0]	\N	2025-12-30 19:40:46	2025-12-30 19:40:46	f
359	56	11	multiple_choice	Quel est le seul pays au monde dont le nom officiel contient un point d'exclamation?	["Saint-Christophe-et-Ni\\u00e9v\\u00e8s","Bosnie-Herz\\u00e9govine","Trinit\\u00e9-et-Tobago","Sao Tom\\u00e9-et-Principe"]	[0]	\N	2025-12-30 19:40:47	2025-12-30 19:40:47	t
360	57	1	multiple_choice	Quel lac d'Europe est connu pour abriter l'île de Bled, célèbre pour son église pittoresque?	["Le lac de Bled","Le lac de C\\u00f4me","Le lac L\\u00e9man","Le lac Majeur"]	[0]	\N	2025-12-30 19:40:48	2025-12-30 19:40:48	f
361	57	2	multiple_choice	Quel parc national français abrite la plus grande concentration de gravures rupestres en plein air d'Europe, témoignant d'une occupation humaine remontant à la Préhistoire ?	["Le Parc national du Mercantour","Le Parc national des \\u00c9crins","Le Parc national de la Vanoise","Le Parc national des C\\u00e9vennes"]	[0]	\N	2025-12-30 19:40:49	2025-12-30 19:40:49	f
362	57	3	true_false	La frontière entre la Belgique et les Pays-Bas présente une particularité unique : elle traverse littéralement des maisons et des commerces, divisant certains bâtiments en deux territoires distincts.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:40:50	2025-12-30 19:40:50	f
363	57	4	true_false	Le désert du Karakoum, situé en Asie centrale, est principalement composé de dunes de sable rouge et est donc impropre à toute forme d'agriculture.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:40:51	2025-12-30 19:40:51	f
364	57	5	true_false	Le point le plus profond mesuré dans l'océan Arctique, appelé le Molloy Deep, est plus profond que le point le plus profond de l'océan Atlantique, la fosse de Porto Rico.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:40:52	2025-12-30 19:40:52	f
365	57	6	multiple_choice	Quel fleuve d'Asie, connu localement sous le nom de 'Mekong Mère', traverse six pays et est vital pour la pêche et l'agriculture de millions de personnes, mais est menacé par la construction de barrages ?	["Le M\\u00e9kong","Le Gange","L'Irrawaddy","Le Salouen"]	[0]	\N	2025-12-30 19:40:53	2025-12-30 19:40:53	f
366	57	7	true_false	La cordillère des Andes abrite le volcan Chimborazo, dont le sommet est le point de la surface terrestre le plus éloigné du centre de la Terre, surpassant ainsi le mont Everest.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:40:54	2025-12-30 19:40:54	f
367	57	8	true_false	L'initiative de la Grande Muraille Verte africaine vise principalement à lutter contre la désertification en plantant une barrière d'arbres à travers tout le continent, du Sénégal à Djibouti.	["Vrai","Faux"]	[0]	\N	2025-12-30 19:40:55	2025-12-30 19:40:55	f
368	57	9	true_false	La capitale administrative de la Bolivie est Sucre, tandis que La Paz est la capitale constitutionnelle.	["Vrai","Faux"]	[1]	\N	2025-12-30 19:40:56	2025-12-30 19:40:56	f
369	57	10	multiple_choice	Quel volcan indonésien, dont l'éruption en 1815 a provoqué une année sans été en raison de la quantité massive de cendres et de dioxyde de soufre injectée dans la stratosphère, est considéré comme l'une des plus grandes éruptions volcaniques de l'histoire récente?	["Le mont Tambora","Le mont Krakatoa","Le mont Merapi","Le mont Agung"]	[0]	\N	2025-12-30 19:40:57	2025-12-30 19:40:57	f
370	57	11	multiple_choice	Quelle île, autrefois un centre majeur de production de guano au XIXe siècle, est aujourd'hui une réserve naturelle abritant une biodiversité marine unique et des colonies d'oiseaux de mer, située au large des côtes du Pérou ?	["\\u00cele Chincha Norte","\\u00cele de P\\u00e2ques","\\u00cele Cocos","\\u00cele Malpelo"]	[0]	\N	2025-12-30 19:40:58	2025-12-30 19:40:58	t
\.


--
-- Data for Name: master_game_teams; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.master_game_teams (id, master_game_id, name, color, team_order, max_players, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: master_games; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.master_games (id, game_code, firebase_id, host_user_id, name, languages, participants_expected, mode, total_questions, question_types, domain_type, theme, school_country, school_level, school_subject, status, current_question, quiz_validated, started_at, ended_at, created_at, updated_at, creation_mode, school_grade, ai_images_count, structure_type, team_count, team_size_cap, skill_policy, buzz_rule) FROM stdin;
1	KQAGPS	\N	3	StrategyBuzzer	["FR"]	10	podium	10	["true_false"]	theme	Géographie	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-16 00:51:39	2025-10-16 00:51:39	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
2	3DRQRQ	\N	3	StrategyBuzzer	["FR"]	10	podium	20	["true_false","multiple_choice"]	theme	Géographie	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-16 01:00:50	2025-10-16 01:00:50	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
3	KPCBN3	\N	3	StrategyBuzzer	["FR"]	10	groups	10	["multiple_choice"]	theme	Géographie	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-16 01:24:59	2025-10-16 01:24:59	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
4	6DLJY2	\N	3	StrategyBuzzer	["FR"]	10	podium	20	["multiple_choice"]	theme	Géographie	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-16 01:25:35	2025-10-16 01:25:35	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
5	LQFMUL	\N	3	StrategyBuzzer1	["EN"]	10	groups	20	["true_false","multiple_choice","image"]	theme	Géographie	Canada	Collège	Mathématiques	draft	0	f	\N	\N	2025-10-16 12:55:01	2025-10-16 12:55:01	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
6	GU9FJL	\N	3	StrategyBuzzer	["FR"]	10	one_vs_all	20	["multiple_choice"]	theme	Géographie	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-16 13:17:34	2025-10-16 13:17:34	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
7	XNEJY8	\N	3	LeFunny	["FR"]	20	groups	10	["multiple_choice"]	theme	Général	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-16 15:13:02	2025-10-16 15:13:02	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
8	CRGXH5	\N	3	Steve Le007	["FR"]	10	one_vs_all	20	["multiple_choice"]	theme	Géographie	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-17 02:13:37	2025-10-17 02:13:37	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
9	VRSFQY	\N	3	LeFunny	["FR"]	10	podium	10	["true_false","multiple_choice","image"]	theme	Géographie	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-17 19:16:45	2025-10-17 19:16:45	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
10	RLOQJ5	\N	3	LeFunny	["FR"]	10	groups	10	["true_false","multiple_choice","image"]	theme	Arts et Culture	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-18 13:50:22	2025-10-18 13:50:22	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
11	FJPEEP	\N	3	LeFunny	["FR"]	10	groups	10	["true_false","multiple_choice","image"]	theme	Géographie	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-18 13:57:34	2025-10-18 13:57:34	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
12	BQLGSL	\N	3	Lefunny	["FR"]	10	groups	10	["true_false","multiple_choice","image"]	theme	Divertissement	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-18 14:24:23	2025-10-18 14:24:23	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
13	GXLR7I	\N	3	LeFunny	["FR"]	10	groups	10	["true_false","multiple_choice","image"]	theme	Sports et Loisirs	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-18 14:41:42	2025-10-18 14:41:42	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
14	OOIY30	\N	3	Lefunny	["FR"]	10	groups	10	["true_false","multiple_choice","image"]	theme	Sports et Loisirs	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-18 15:19:08	2025-10-18 15:19:08	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
15	DSVLL7	\N	3	Lefunny	["FR"]	10	groups	10	["true_false","multiple_choice","image"]	theme	Géographie	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-18 18:31:09	2025-10-18 18:31:09	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
16	SNMPQK	\N	3	LeFunny	["FR"]	10	groups	20	["true_false","multiple_choice"]	theme	Sciences et Nature	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-18 18:39:51	2025-10-18 18:39:51	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
17	02T8GI	\N	3	LeFunny	["FR"]	10	one_vs_all	10	["multiple_choice"]	theme	Technologie	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-18 19:23:02	2025-10-18 19:23:02	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
18	FFJEKU	\N	3	Lefunny	["FR"]	10	one_vs_all	20	["multiple_choice"]	theme	Société	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-18 19:23:46	2025-10-18 19:23:46	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
19	DXSU9S	\N	3	LeFunny	["FR"]	10	groups	10	["true_false","multiple_choice","image"]	theme	Technologie	France	Primaire	Mathématiques	draft	0	f	\N	\N	2025-10-18 19:45:13	2025-10-18 19:45:13	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
20	KS7WSH	\N	3	LeFunny	["FR"]	10	groups	20	["true_false","multiple_choice","image"]	scolaire	Géographie	Canada	Secondaire	Éducation physique	draft	0	f	\N	\N	2025-10-18 20:49:56	2025-10-18 20:49:56	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
21	D5AKPU	\N	3	StrategyBuzzer	["FR"]	10	groups	10	["true_false","multiple_choice"]	scolaire	Géographie	Canada	Secondaire	Mathématiques	draft	0	f	\N	\N	2025-10-20 13:12:02	2025-10-20 13:12:02	personnalise	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
22	IJU7UJ	\N	3	StrategyBuzzer	["EN"]	10	groups	10	["true_false","multiple_choice"]	theme	Divertissement	\N	\N	\N	draft	0	f	\N	\N	2025-10-20 13:21:05	2025-10-20 13:21:05	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
23	R7JNXK	\N	3	StrategyBuzzer	["FR"]	10	one_vs_all	10	["true_false","multiple_choice"]	scolaire	Géographie	Canada	Secondaire	Mathématiques	draft	0	f	\N	\N	2025-11-27 00:14:29	2025-11-27 00:14:29	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
24	GJXLM4	\N	3	StrategyBuzzer	["FR"]	10	one_vs_all	10	["true_false","multiple_choice"]	scolaire	Géographie	Canada	Secondaire	Mathématiques	draft	0	f	\N	\N	2025-11-27 00:14:35	2025-11-27 00:14:35	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
25	PPW9KO	\N	3	StrategyBuzzer	["FR"]	10	one_vs_all	10	["true_false","multiple_choice"]	scolaire	Géographie	Canada	Secondaire	Mathématiques	draft	0	f	\N	\N	2025-11-27 00:14:39	2025-11-27 00:14:39	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
26	EMC2CK	\N	3	StrategyBuzzer	["FR"]	10	one_vs_all	10	["true_false","multiple_choice"]	scolaire	Géographie	Canada	Secondaire	Mathématiques	draft	0	f	\N	\N	2025-11-27 00:14:41	2025-11-27 00:14:41	personnalise	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
27	ZDEQ6S	\N	3	StrategyBuzzer	["FR"]	10	one_vs_all	10	["true_false","multiple_choice"]	scolaire	Géographie	Canada	Secondaire	Mathématiques	draft	0	f	\N	\N	2025-11-27 00:14:44	2025-11-27 00:14:44	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
28	NHKBPX	\N	3	StrategyBuzzer	["FR"]	10	one_vs_all	10	["true_false","multiple_choice"]	scolaire	Géographie	Canada	Secondaire	Mathématiques	draft	0	f	\N	\N	2025-11-27 00:14:47	2025-11-27 00:14:47	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
29	VGVFBR	\N	3	StrategyBuzzer	["FR"]	10	one_vs_all	10	["true_false","multiple_choice"]	scolaire	Géographie	Canada	Secondaire	Mathématiques	draft	0	f	\N	\N	2025-11-27 00:14:49	2025-11-27 00:14:49	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
30	BDCLCU	\N	3	StrategyBuzzer	["FR"]	10	one_vs_all	10	["true_false","multiple_choice"]	scolaire	Géographie	Canada	Secondaire	Mathématiques	draft	0	f	\N	\N	2025-11-27 00:14:51	2025-11-27 00:14:51	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
31	AM64S5	\N	3	StrategyBuzzer	["FR"]	10	one_vs_all	10	["true_false","multiple_choice"]	scolaire	Géographie	Canada	Secondaire	Mathématiques	draft	0	f	\N	\N	2025-11-27 00:14:53	2025-11-27 00:14:53	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
32	T4JCIR	\N	3	StrategyBuzzer	["FR"]	10	one_vs_all	10	["true_false","multiple_choice"]	scolaire	Géographie	Canada	Secondaire	Mathématiques	draft	0	f	\N	\N	2025-11-27 00:14:56	2025-11-27 00:14:56	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
33	87RGEJ	\N	3	StrategyBuzzer	["FR"]	10	one_vs_all	10	["multiple_choice"]	scolaire	Géographie	Canada	Cégep	Histoire	draft	0	f	\N	\N	2025-11-27 00:47:28	2025-11-27 00:47:28	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
34	GPF0IL	\N	3	StrategyBuzzer	["FR"]	10	one_vs_all	10	["multiple_choice"]	scolaire	Géographie	Canada	Cégep	Histoire	draft	0	f	\N	\N	2025-11-27 00:47:33	2025-11-27 00:47:33	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
35	RGSNBV	\N	3	Steve Le007	["FR"]	10	one_vs_all	20	["true_false","multiple_choice"]	scolaire	Géographie	Canada	Secondaire	Histoire	draft	0	f	\N	\N	2025-11-27 00:53:45	2025-11-27 00:53:45	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
36	ET23P0	\N	3	StrategyBuzzer	["FR"]	10	one_vs_all	20	["true_false","multiple_choice","image"]	theme	Géographie	\N	\N	\N	draft	0	f	\N	\N	2025-11-27 01:42:38	2025-11-27 01:42:38	automatique	\N	3	free_for_all	\N	20	all_players	first_buzz_locks
37	7P9NEH	\N	3	StrategyBuzzer	["FR"]	10	one_vs_all	20	["true_false","multiple_choice"]	theme	Géographie	\N	\N	\N	draft	0	f	\N	\N	2025-11-27 01:50:30	2025-11-27 01:50:30	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
38	PHMOZO	\N	3	StrategyBuzzer	["FR"]	10	one_vs_all	20	["true_false","multiple_choice"]	theme	Géographie	\N	\N	\N	draft	0	f	\N	\N	2025-11-27 02:05:28	2025-11-27 02:05:28	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
39	AIJS5Y	\N	3	Steve Desrosiers	["FR"]	10	one_vs_all	20	["multiple_choice"]	theme	Géographie	\N	\N	\N	draft	0	f	\N	\N	2025-11-27 02:18:17	2025-11-27 02:18:17	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
40	WO7WPV	\N	3	StrategyBuzzer	["FR"]	4	podium	10	["true_false","multiple_choice"]	theme	Sciences et Nature	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:19:07	2025-12-30 19:19:07	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
41	GWFFPL	\N	3	StrategyBuzzer	["FR"]	4	podium	10	["true_false","multiple_choice"]	theme	Sciences et Nature	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:19:19	2025-12-30 19:19:19	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
42	PPS3W5	\N	3	StrategyBuzzer	["FR"]	4	podium	10	["true_false","multiple_choice"]	theme	Sciences et Nature	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:19:32	2025-12-30 19:19:32	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
43	WVWUD6	\N	3	StrategyBuzzer	["FR"]	4	podium	10	["true_false","multiple_choice"]	theme	Sciences et Nature	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:19:44	2025-12-30 19:19:44	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
44	ESX9KF	\N	3	StrategyBuzzer	["FR"]	4	podium	10	["true_false","multiple_choice"]	theme	Sciences et Nature	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:19:56	2025-12-30 19:19:56	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
45	THZZ9T	\N	3	StrategyBuzzer	["FR"]	4	podium	10	["true_false","multiple_choice"]	theme	Sciences et Nature	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:20:07	2025-12-30 19:20:07	personnalise	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
46	RK22XK	\N	3	StrategyBuzzer	["FR"]	3	one_vs_all	10	["true_false","multiple_choice"]	theme	Géographie	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:37:59	2025-12-30 19:37:59	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
47	NFAKND	\N	3	StrategyBuzzer	["FR"]	3	one_vs_all	10	["true_false","multiple_choice"]	theme	Géographie	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:38:11	2025-12-30 19:38:11	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
48	WEIZK2	\N	3	StrategyBuzzer	["FR"]	3	one_vs_all	10	["true_false","multiple_choice"]	theme	Géographie	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:38:22	2025-12-30 19:38:22	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
49	SUCPQS	\N	3	StrategyBuzzer	["FR"]	3	one_vs_all	10	["true_false","multiple_choice"]	theme	Géographie	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:38:33	2025-12-30 19:38:33	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
50	MP5QLM	\N	3	StrategyBuzzer	["FR"]	3	one_vs_all	10	["true_false","multiple_choice"]	theme	Géographie	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:38:44	2025-12-30 19:38:44	personnalise	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
51	XKLDTA	\N	3	StrategyBuzzer	["FR"]	3	one_vs_all	10	["true_false","multiple_choice"]	theme	Géographie	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:38:45	2025-12-30 19:38:45	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
52	HNTVCQ	\N	3	StrategyBuzzer	["FR"]	4	one_vs_all	10	["true_false","multiple_choice"]	theme	Géographie	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:40:03	2025-12-30 19:40:03	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
53	HGLCZJ	\N	3	StrategyBuzzer	["FR"]	4	one_vs_all	10	["true_false","multiple_choice"]	theme	Géographie	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:40:15	2025-12-30 19:40:15	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
54	9WZQX8	\N	3	StrategyBuzzer	["FR"]	4	one_vs_all	10	["true_false","multiple_choice"]	theme	Géographie	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:40:25	2025-12-30 19:40:25	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
55	OGESGS	\N	3	StrategyBuzzer	["FR"]	4	one_vs_all	10	["true_false","multiple_choice"]	theme	Géographie	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:40:36	2025-12-30 19:40:36	personnalise	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
56	SDJNVN	\N	3	StrategyBuzzer	["FR"]	4	one_vs_all	10	["true_false","multiple_choice"]	theme	Géographie	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:40:37	2025-12-30 19:40:37	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
57	CAT17V	\N	3	StrategyBuzzer	["FR"]	4	one_vs_all	10	["true_false","multiple_choice"]	theme	Géographie	\N	\N	\N	draft	0	f	\N	\N	2025-12-30 19:40:47	2025-12-30 19:40:47	automatique	\N	0	free_for_all	\N	20	all_players	first_buzz_locks
\.


--
-- Data for Name: match_performances; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.match_performances (id, user_id, game_mode, game_id, performance, rounds_played, is_victory, played_at, created_at, updated_at) FROM stdin;
1	3	solo	solo_57_1762980189	-22.22	2	f	2025-11-12 20:43:10	2025-11-12 20:43:10	2025-11-12 20:43:10
2	3	solo	solo_17_1763056788	60.00	1	f	2025-11-13 17:59:49	2025-11-13 17:59:49	2025-11-13 17:59:49
3	3	solo	solo_17_1763057135	55.00	2	t	2025-11-13 18:05:35	2025-11-13 18:05:35	2025-11-13 18:05:35
4	6	solo	solo_1_1763467839	45.00	2	t	2025-11-18 12:10:39	2025-11-18 12:10:39	2025-11-18 12:10:39
5	3	solo	solo_10_1763562376	22.73	2	f	2025-11-19 14:26:16	2025-11-19 14:26:16	2025-11-19 14:26:16
6	3	solo	solo_10_1763593612	50.00	2	f	2025-11-19 23:06:52	2025-11-19 23:06:52	2025-11-19 23:06:52
7	3	solo	solo_10_1763597871	20.00	2	f	2025-11-20 00:17:51	2025-11-20 00:17:51	2025-11-20 00:17:51
8	3	solo	solo_10_1763609133	40.00	3	f	2025-11-20 03:25:33	2025-11-20 03:25:33	2025-11-20 03:25:33
9	3	solo	solo_20_1763665668	75.00	2	t	2025-11-20 19:07:48	2025-11-20 19:07:48	2025-11-20 19:07:48
10	3	solo	solo_30_1763688204	23.33	3	f	2025-11-21 01:23:25	2025-11-21 01:23:25	2025-11-21 01:23:25
11	3	solo	solo_10_1763826219	70.00	2	t	2025-11-22 15:43:40	2025-11-22 15:43:40	2025-11-22 15:43:40
12	3	solo	solo_16_1763853352	60.00	2	t	2025-11-22 23:15:52	2025-11-22 23:15:52	2025-11-22 23:15:52
13	3	solo	solo_5_1763926325	60.00	2	t	2025-11-23 19:32:06	2025-11-23 19:32:06	2025-11-23 19:32:06
14	3	solo	solo_5_1763927442	60.00	2	t	2025-11-23 19:50:43	2025-11-23 19:50:43	2025-11-23 19:50:43
15	3	solo	solo_49_1765427470	20.00	2	t	2025-12-11 04:31:10	2025-12-11 04:31:10	2025-12-11 04:31:10
16	10	solo	solo_1_1766183077	-10.00	2	t	2025-12-19 22:24:37	2025-12-19 22:24:37	2025-12-19 22:24:37
17	4	solo	solo_1_1767476918	50.00	2	t	2026-01-03 21:48:39	2026-01-03 21:48:39	2026-01-03 21:48:39
18	3	solo	solo_12_1768740533	-5.00	2	f	2026-01-18 12:48:53	2026-01-18 12:48:53	2026-01-18 12:48:53
19	3	solo	solo_12_1768742105	20.00	2	t	2026-01-18 13:15:05	2026-01-18 13:15:05	2026-01-18 13:15:05
20	3	solo	solo_10_1769226034	-20.00	2	f	2026-01-24 03:40:35	2026-01-24 03:40:35	2026-01-24 03:40:35
21	3	solo	solo_10_1769228629	-2.50	2	f	2026-01-24 04:23:50	2026-01-24 04:23:50	2026-01-24 04:23:50
22	3	solo	solo_10_1769283392	17.50	2	f	2026-01-24 19:36:33	2026-01-24 19:36:33	2026-01-24 19:36:33
23	3	solo	solo_7_1769428510	37.50	2	t	2026-01-26 11:55:10	2026-01-26 11:55:10	2026-01-26 11:55:10
24	3	solo	solo_7_1769439102	20.00	3	t	2026-01-26 14:51:43	2026-01-26 14:51:43	2026-01-26 14:51:43
25	3	solo	solo_28_1770121375	-35.00	3	f	2026-02-03 12:22:56	2026-02-03 12:22:56	2026-02-03 12:22:56
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	2014_10_12_100000_create_password_reset_tokens_table	1
2	2019_08_19_000000_create_failed_jobs_table	1
3	2019_12_14_000001_create_personal_access_tokens_table	1
4	2025_07_05_000000_create_users_table	1
5	2025_07_17_172326_modify_users_password_nullable	1
6	2025_07_17_173236_modify_users_password_nullable	1
7	2025_07_17_200722_create_invitations_table	1
8	2025_07_17_223223_modify_users_password_nullable	1
9	2025_07_17_xxxxxx_modify_users_password_nullable	1
10	2025_09_08_063556_create_user_avatars_table	1
11	2025_09_15_000000_add_game_fields_to_users_and_purchases	1
12	2025_10_02_114438_create_payments_table	1
13	2025_10_02_114439_create_coin_ledger_table	1
14	2025_10_02_185102_add_intelligence_pieces_to_users_table	1
15	2025_10_12_223420_create_duo_matches_table	2
16	2025_10_12_223420_create_player_duo_stats_table	2
17	2025_10_12_223421_create_player_divisions_table	2
18	2025_10_12_231529_create_league_individual_tables	3
19	2025_10_12_233745_create_teams_table	4
20	2025_10_12_233746_create_league_team_matches_table	4
21	2025_10_12_233746_create_team_invitations_table	4
22	2025_10_12_233747_create_team_members_table	4
23	2025_10_13_160552_create_quests_table	5
24	2025_10_13_160553_create_user_quest_progress_table	5
25	2025_10_13_191650_add_profile_completed_to_users_table	6
26	2025_10_13_221557_add_master_purchased_to_users_table	7
27	2025_10_14_101025_add_player_code_to_users_table	8
28	2025_10_15_223649_create_master_games_table	9
29	2025_10_15_223650_create_master_game_codes_table	9
30	2025_10_15_223650_create_master_game_questions_table	9
31	2025_10_15_223651_create_master_game_players_table	9
32	2025_10_16_012644_add_creation_mode_to_master_games_table	10
33	2025_10_24_211613_add_next_life_regen_to_users_table	11
34	2025_10_25_181806_update_quests_table_for_new_quest_system	12
35	2025_10_26_222113_create_daily_quest_rotation_table	13
36	2025_10_26_222147_add_columns_to_daily_quest_rotation_table	14
37	2025_10_27_220247_create_player_statistics_table	14
38	2025_11_04_011326_add_music_preferences_to_users_table	15
39	2025_11_04_014131_create_question_history_table	16
40	2025_11_04_215104_rename_efficacite_brute_to_efficacite_joueur_in_player_statistics	17
41	2025_11_04_215132_add_efficacite_columns_to_player_statistics	17
42	2025_11_12_195609_create_profile_stats_table	18
43	2025_11_12_195610_create_match_performances_table	18
44	2025_11_14_164440_create_player_contacts_table	19
45	2025_11_23_180603_add_preferred_language_to_users_table	20
46	2025_11_27_012300_add_ai_images_count_to_master_games_table	21
47	2025_11_27_021135_add_is_tiebreaker_to_master_game_questions_table	22
48	2025_12_02_000001_create_player_messages_table	23
49	2025_12_03_020000_add_game_modes_purchased_to_users_table	24
50	2025_12_11_065735_add_efficiency_to_player_divisions	25
51	2025_12_11_093342_add_temporary_access_to_users_table	26
52	2025_12_11_094107_add_coins_earned_to_duo_matches_table	27
53	2025_12_11_095241_add_coins_to_league_individual_matches_table	28
54	2025_12_11_101908_add_competence_coins_to_users_table	29
55	2025_12_15_120000_create_lobby_game_starts_table	30
56	2025_12_16_024630_create_jobs_table	31
57	2025_12_17_101511_add_game_mode_to_league_team_matches_table	32
58	2025_12_17_102648_change_current_player_index_to_json_in_league_team_matches	33
59	2025_12_19_023025_add_match_division_to_league_team_matches	34
60	2025_12_19_202333_change_default_lives_to_3	35
61	2025_12_30_031421_add_structure_to_master_games	36
62	2026_01_02_193321_add_room_id_to_duo_matches_table	37
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: payments; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.payments (id, user_id, stripe_session_id, stripe_payment_intent_id, product_key, amount_cents, currency, coins_awarded, status, metadata, processed_at, created_at, updated_at) FROM stdin;
1	3	cs_test_a1J3bkygtXF5hGTKKRHcJ0D5oWTIHUFOshiBcVsqHMgsQjDOfnCOhlL4V7	\N	starter	214	usd	0	pending	{"coins":100,"pack_name":"Pack D\\u00e9butant"}	\N	2025-10-12 22:56:25	2025-10-12 22:56:25
2	3	cs_test_a1i5ebMZuXeEsIoNWPK7Iqvno3Ehh12zjfKVWf8s1Gvw8XaNJ5WAxIxe67	\N	starter	214	usd	0	pending	{"coins":100,"pack_name":"Pack D\\u00e9butant"}	\N	2025-10-12 22:56:28	2025-10-12 22:56:28
3	4	cs_test_a1w6aolnhYRN0aLT72rYF4MNuW4tAYxBwohBnnkuDz3rZrqBkB8bbBdoNa	\N	master_mode	2999	usd	0	pending	{"product_type":"master_mode","product_name":"Mode Ma\\u00eetre du Jeu"}	\N	2025-10-13 21:57:35	2025-10-13 21:57:35
4	4	cs_test_a1XBNUzn9d2dqlYUNLvfltGpaz9vaWNyf6q3rrznr0kze5sFdq8Lt80eUA	\N	master_mode	2999	usd	0	pending	{"product_type":"master_mode","product_name":"Mode Ma\\u00eetre du Jeu"}	\N	2025-10-13 21:58:36	2025-10-13 21:58:36
5	4	cs_test_a1mKfWhlaW6iEh2FWgVX0P4mfhri5rmEi28PTzBqlAFvhhNDcdkZXcbaAK	\N	starter	214	usd	0	pending	{"coins":100,"pack_name":"Pack D\\u00e9butant"}	\N	2025-10-13 22:01:48	2025-10-13 22:01:48
6	4	cs_test_a1JPYI6vxr9DpmpjO4hVCraCfNKqOn3OWrKluNLSFNF8exWuNcDfh62Bmz	\N	master_mode	2999	usd	0	pending	{"product_type":"master_mode","product_name":"Mode Ma\\u00eetre du Jeu"}	\N	2025-10-13 22:09:23	2025-10-13 22:09:23
7	7	cs_test_a1iyHzrcqEF17j6pLAErxaO83wxxgy9NGlFi9P3pBxjEOAnhvmff9txLff	\N	master_mode	2999	usd	0	pending	{"product_type":"master_mode","product_name":"Mode Ma\\u00eetre du Jeu"}	\N	2025-10-16 00:08:31	2025-10-16 00:08:31
8	3	cs_test_a158n7An1no7wxcVYbzPo9ggaO0VUzyIVY86nbmWrzooK7lnU9hydDHLtg	\N	competence_mega	6500	usd	0	pending	{"coins":500,"pack_name":"Pack Mega","coin_type":"competence"}	\N	2025-12-12 04:00:03	2025-12-12 04:00:03
9	3	cs_test_a1eYQ8M33SI853hXwx8SgfRdyNoAEhQ8cVyuscMLNdiffJB0iD0KCNA5aI	\N	intelligence_starter	99	usd	0	pending	{"coins":100,"pack_name":"Pack D\\u00e9butant","coin_type":"intelligence"}	\N	2025-12-12 04:01:46	2025-12-12 04:01:46
10	3	cs_test_a1ORnD5OjOYgmpv0qy2LBpLiIGJOoyoY88p2OMzTmsHFlaeTbAuKho8i3l	\N	competence_starter	1000	usd	0	pending	{"coins":50,"pack_name":"Pack Starter","coin_type":"competence"}	\N	2025-12-12 05:35:54	2025-12-12 05:35:54
11	3	cs_test_a15V7P3TvD7bazT4QqO57u2U59ivVSZ0EehQiCuhqLKJPDnU5JxIXWfHjx	\N	competence_starter	1000	cad	0	pending	{"coins":50,"pack_name":"Pack Starter","coin_type":"competence"}	\N	2025-12-12 06:31:57	2025-12-12 06:31:57
\.


--
-- Data for Name: personal_access_tokens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: player_contacts; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.player_contacts (id, user_id, contact_user_id, matches_played_together, matches_won, matches_lost, decisive_rounds_played, decisive_rounds_won, last_played_at, created_at, updated_at) FROM stdin;
1	3	6	0	0	0	0	0	2025-12-02 00:20:08	2025-12-02 00:20:08	2025-12-02 00:20:08
2	6	3	0	0	0	0	0	2025-12-02 00:20:09	2025-12-02 00:20:09	2025-12-02 00:20:09
3	8	3	0	0	0	0	0	2025-12-08 20:27:09	2025-12-08 20:27:09	2025-12-08 20:27:09
4	3	8	0	0	0	0	0	2025-12-08 20:27:09	2025-12-08 20:27:09	2025-12-08 20:27:09
5	5	3	0	0	0	0	0	2025-12-08 20:32:43	2025-12-08 20:32:43	2025-12-08 20:32:43
6	3	5	0	0	0	0	0	2025-12-08 20:32:43	2025-12-08 20:32:43	2025-12-08 20:32:43
7	5	8	0	0	0	0	0	2025-12-08 20:32:43	2025-12-08 20:32:44	2025-12-08 20:32:44
8	8	5	0	0	0	0	0	2025-12-08 20:32:44	2025-12-08 20:32:44	2025-12-08 20:32:44
9	7	3	0	0	0	0	0	2025-12-08 20:35:25	2025-12-08 20:35:25	2025-12-08 20:35:25
10	3	7	0	0	0	0	0	2025-12-08 20:35:25	2025-12-08 20:35:25	2025-12-08 20:35:25
11	7	5	0	0	0	0	0	2025-12-08 20:35:25	2025-12-08 20:35:25	2025-12-08 20:35:25
12	5	7	0	0	0	0	0	2025-12-08 20:35:25	2025-12-08 20:35:25	2025-12-08 20:35:25
13	7	8	0	0	0	0	0	2025-12-08 20:35:25	2025-12-08 20:35:25	2025-12-08 20:35:25
14	8	7	0	0	0	0	0	2025-12-08 20:35:25	2025-12-08 20:35:25	2025-12-08 20:35:25
15	4	3	0	0	0	0	0	2025-12-08 20:38:58	2025-12-08 20:38:58	2025-12-08 20:38:58
16	3	4	0	0	0	0	0	2025-12-08 20:38:58	2025-12-08 20:38:58	2025-12-08 20:38:58
17	4	5	0	0	0	0	0	2025-12-08 20:38:58	2025-12-08 20:38:58	2025-12-08 20:38:58
18	5	4	0	0	0	0	0	2025-12-08 20:38:58	2025-12-08 20:38:58	2025-12-08 20:38:58
19	4	7	0	0	0	0	0	2025-12-08 20:38:58	2025-12-08 20:38:58	2025-12-08 20:38:58
20	7	4	0	0	0	0	0	2025-12-08 20:38:58	2025-12-08 20:38:59	2025-12-08 20:38:59
21	4	8	0	0	0	0	0	2025-12-08 20:38:59	2025-12-08 20:38:59	2025-12-08 20:38:59
22	8	4	0	0	0	0	0	2025-12-08 20:38:59	2025-12-08 20:38:59	2025-12-08 20:38:59
23	3	9	0	0	0	0	0	2025-12-17 05:52:06	2025-12-17 05:52:06	2025-12-17 05:52:06
24	9	3	0	0	0	0	0	2025-12-17 05:52:07	2025-12-17 05:52:07	2025-12-17 05:52:07
\.


--
-- Data for Name: player_divisions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.player_divisions (id, user_id, mode, division, points, level, rank, created_at, updated_at, initial_efficiency, matches_won, matches_lost) FROM stdin;
1	3	league_individual	bronze	0	1	\N	2025-10-13 00:06:15	2025-10-13 00:06:15	0.00	0	0
5	4	league_individual	bronze	0	1	\N	2025-12-09 17:27:34	2025-12-09 17:27:34	0.00	0	0
2	3	duo	bronze	0	1	1	2025-10-26 05:11:39	2025-10-26 05:11:39	0.00	0	0
3	6	duo	bronze	0	1	2	2025-12-01 23:10:10	2025-12-15 00:37:23	0.00	0	0
4	5	duo	bronze	0	1	3	2025-12-08 20:32:31	2025-12-15 00:37:23	0.00	0	0
6	4	duo	bronze	0	1	4	2025-12-09 18:01:23	2025-12-15 00:37:23	0.00	0	0
9	9	duo	bronze	0	1	7	2025-12-17 05:51:11	2025-12-17 05:51:11	0.00	0	0
8	8	duo	bronze	0	1	5	2025-12-09 18:02:05	2025-12-19 22:14:10	0.00	0	0
7	7	duo	bronze	0	1	6	2025-12-09 18:02:05	2025-12-19 22:14:10	0.00	0	0
10	10	duo	bronze	0	1	8	2025-12-19 22:14:10	2025-12-19 22:14:10	0.00	0	0
\.


--
-- Data for Name: player_duo_stats; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.player_duo_stats (id, user_id, total_matches, victories, defeats, level, win_rate, current_streak, best_win_streak, best_lose_streak, created_at, updated_at) FROM stdin;
1	3	0	0	0	0	0.00	0	0	0	2025-10-13 00:31:32	2025-10-13 00:31:32
2	8	0	0	0	0	0.00	0	0	0	2025-11-17 15:00:45	2025-11-17 15:00:45
3	6	0	0	0	0	0.00	0	0	0	2025-12-01 20:40:57	2025-12-01 20:40:57
4	5	0	0	0	0	0.00	0	0	0	2025-12-08 20:32:31	2025-12-08 20:32:31
5	4	0	0	0	0	0.00	0	0	0	2025-12-09 18:01:23	2025-12-09 18:01:23
6	9	0	0	0	0	0.00	0	0	0	2025-12-17 05:51:11	2025-12-17 05:51:11
7	10	0	0	0	0	0.00	0	0	0	2025-12-19 22:14:10	2025-12-19 22:14:10
\.


--
-- Data for Name: player_group_members; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.player_group_members (id, group_id, member_user_id, created_at) FROM stdin;
\.


--
-- Data for Name: player_groups; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.player_groups (id, user_id, name, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: player_messages; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.player_messages (id, sender_id, receiver_id, message, is_read, related_match_id, created_at, updated_at) FROM stdin;
1	3	6	salut	t	\N	2025-12-02 22:21:04	2025-12-03 00:40:25
2	6	3	Salut	t	\N	2025-12-06 18:59:00	2025-12-06 18:59:08
3	3	6	salut	t	\N	2025-12-06 18:59:22	2025-12-06 21:38:17
4	3	6	salut	t	\N	2025-12-06 21:38:13	2025-12-06 21:38:17
5	6	3	Rerr	t	\N	2025-12-06 21:58:28	2025-12-06 21:58:36
6	4	3	Allo	t	\N	2025-12-11 22:36:58	2025-12-11 22:37:03
7	3	9	Salut minou	t	\N	2025-12-20 02:14:24	2025-12-20 02:14:33
8	9	3	Salut	t	\N	2025-12-20 02:14:44	2025-12-20 02:15:46
9	3	4	Allo	t	\N	2026-01-02 16:08:37	2026-01-02 16:08:50
\.


--
-- Data for Name: player_statistics; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.player_statistics (id, user_id, game_mode, scope, game_id, round_number, total_questions, questions_buzzed, correct_answers, wrong_answers, points_earned, points_possible, efficacite_joueur, taux_participation, taux_precision, ratio_performance, details, created_at, updated_at, efficacite_partie, efficacite_manche) FROM stdin;
1	3	solo	match	solo_10_1761848283	\N	19	13	9	4	10	38	26.32	68.42	69.23	26.32	\N	2025-10-30 18:18:03	2025-10-30 18:18:03	\N	\N
3	3	solo	match	solo_10_1762286493	\N	29	29	21	8	26	58	44.83	100.00	72.41	44.83	\N	2025-11-04 20:01:33	2025-11-04 20:01:33	\N	\N
4	3	solo	match	solo_10_1762289553	\N	22	22	13	9	8	44	18.18	100.00	59.09	18.18	\N	2025-11-04 20:52:33	2025-11-04 20:52:33	\N	\N
31	3	solo	match	solo_10_1769228629	\N	20	14	7	7	-1	40	0.00	70.00	50.00	-2.50	\N	2026-01-24 04:23:49	2026-01-24 04:23:49	-2.50	\N
5	3	solo	match	solo_10_1762293083	\N	22	22	13	9	8	44	18.18	100.00	59.09	18.18	\N	2025-11-04 21:51:23	2025-11-04 21:51:23	\N	\N
6	3	solo	match	solo_7_1762963589	\N	22	17	14	3	22	44	0.00	77.27	82.35	50.00	\N	2025-11-12 16:06:29	2025-11-12 16:06:29	0.00	\N
32	3	solo	match	solo_10_1769283392	\N	20	16	11	5	7	40	0.00	80.00	68.75	17.50	\N	2026-01-24 19:36:32	2026-01-24 19:36:32	17.50	\N
7	3	solo	match	solo_7_1762970685	\N	18	16	13	3	20	36	0.00	88.89	81.25	55.56	\N	2025-11-12 18:04:45	2025-11-12 18:04:45	0.00	\N
8	3	solo	match	solo_57_1762980189	\N	18	16	6	10	-8	36	0.00	88.89	37.50	-22.22	\N	2025-11-12 20:43:09	2025-11-12 20:43:09	-22.22	\N
33	3	solo	match	solo_7_1769428510	\N	20	16	12	4	15	40	0.00	80.00	75.00	37.50	\N	2026-01-26 11:55:10	2026-01-26 11:55:10	37.50	\N
9	3	solo	match	solo_17_1763056788	\N	10	10	8	2	12	20	0.00	100.00	80.00	60.00	\N	2025-11-13 17:59:48	2025-11-13 17:59:48	60.00	\N
10	3	solo	match	solo_17_1763057135	\N	20	19	15	4	22	40	0.00	95.00	78.95	55.00	\N	2025-11-13 18:05:35	2025-11-13 18:05:35	55.00	\N
34	3	solo	match	solo_7_1769439102	\N	30	13	10	3	12	60	0.00	43.33	76.92	20.00	\N	2026-01-26 14:51:42	2026-01-26 14:51:42	20.00	\N
11	6	solo	match	solo_1_1763467839	\N	20	17	13	4	18	40	0.00	85.00	76.47	45.00	\N	2025-11-18 12:10:39	2025-11-18 12:10:39	45.00	\N
12	6	solo	global	\N	\N	20	17	13	4	18	40	45.00	85.00	76.47	45.00	\N	2025-11-18 12:10:39	2025-11-18 12:10:39	\N	\N
13	3	solo	match	solo_10_1763562376	\N	22	21	13	8	10	44	0.00	95.45	61.90	22.73	\N	2025-11-19 14:26:16	2025-11-19 14:26:16	22.73	\N
14	3	solo	match	solo_10_1763593612	\N	20	18	14	4	20	40	0.00	90.00	77.78	50.00	\N	2025-11-19 23:06:52	2025-11-19 23:06:52	50.00	\N
35	3	solo	match	solo_28_1770121375	\N	30	25	8	17	-21	60	0.00	83.33	32.00	-35.00	\N	2026-02-03 12:22:55	2026-02-03 12:22:55	-35.00	\N
15	3	solo	match	solo_10_1763597871	\N	20	18	11	7	8	40	0.00	90.00	61.11	20.00	\N	2025-11-20 00:17:51	2025-11-20 00:17:51	20.00	\N
2	3	solo	global	\N	\N	602	511	346	165	344	1204	11.25	84.88	67.71	28.57	\N	2025-10-30 18:18:04	2026-02-03 12:22:55	\N	\N
16	3	solo	match	solo_10_1763609133	\N	30	28	20	8	24	60	0.00	93.33	71.43	40.00	\N	2025-11-20 03:25:33	2025-11-20 03:25:33	40.00	\N
17	3	solo	match	solo_20_1763665668	\N	20	19	17	2	30	40	0.00	95.00	89.47	75.00	\N	2025-11-20 19:07:48	2025-11-20 19:07:48	75.00	\N
18	3	solo	match	solo_30_1763688204	\N	30	29	18	11	14	60	0.00	96.67	62.07	23.33	\N	2025-11-21 01:23:24	2025-11-21 01:23:24	23.33	\N
19	3	solo	match	solo_10_1763826219	\N	20	18	16	2	28	40	0.00	90.00	88.89	70.00	\N	2025-11-22 15:43:39	2025-11-22 15:43:39	70.00	\N
20	3	solo	match	solo_16_1763853352	\N	20	18	15	3	24	40	0.00	90.00	83.33	60.00	\N	2025-11-22 23:15:52	2025-11-22 23:15:52	60.00	\N
21	3	solo	match	solo_5_1763926325	\N	20	20	16	4	24	40	0.00	100.00	80.00	60.00	\N	2025-11-23 19:32:05	2025-11-23 19:32:05	60.00	\N
22	3	solo	match	solo_5_1763927442	\N	20	20	16	4	24	40	0.00	100.00	80.00	60.00	\N	2025-11-23 19:50:42	2025-11-23 19:50:42	60.00	\N
23	3	solo	match	solo_49_1765427470	\N	20	16	10	6	8	40	0.00	80.00	62.50	20.00	\N	2025-12-11 04:31:10	2025-12-11 04:31:10	20.00	\N
24	10	solo	match	solo_1_1766183077	\N	20	19	9	10	-4	40	0.00	95.00	47.37	-10.00	\N	2025-12-19 22:24:37	2025-12-19 22:24:37	-10.00	\N
25	10	solo	global	\N	\N	20	19	9	10	-4	40	-10.00	95.00	47.37	-10.00	\N	2025-12-19 22:24:37	2025-12-19 22:24:37	\N	\N
26	4	solo	match	solo_1_1767476918	\N	20	19	15	4	20	40	0.00	95.00	78.95	50.00	\N	2026-01-03 21:48:38	2026-01-03 21:48:38	50.00	\N
27	4	solo	global	\N	\N	20	19	15	4	20	40	50.00	95.00	78.95	50.00	\N	2026-01-03 21:48:39	2026-01-03 21:48:39	\N	\N
28	3	solo	match	solo_12_1768740533	\N	20	16	8	8	-2	40	0.00	80.00	50.00	-5.00	\N	2026-01-18 12:48:53	2026-01-18 12:48:53	-5.00	\N
29	3	solo	match	solo_12_1768742105	\N	20	13	9	4	8	40	0.00	65.00	69.23	20.00	\N	2026-01-18 13:15:05	2026-01-18 13:15:05	20.00	\N
30	3	solo	match	solo_10_1769226034	\N	20	9	3	6	-8	40	0.00	45.00	33.33	-20.00	\N	2026-01-24 03:40:34	2026-01-24 03:40:34	-20.00	\N
\.


--
-- Data for Name: profile_stats; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.profile_stats (id, user_id, solo_matchs_joues, solo_victoires, solo_defaites, solo_ratio_victoire, solo_matchs_3_manches, solo_victoires_3_manches, solo_performance_moyenne, duo_matchs_joues, duo_victoires, duo_defaites, duo_ratio_victoire, duo_performance_moyenne, league_matchs_joues, league_victoires, league_defaites, league_ratio_victoire, league_performance_moyenne, created_at, updated_at, solo_efficacite_joueur, duo_efficacite_joueur, league_efficacite_joueur) FROM stdin;
3	10	1	1	0	100.00	0	0	-10.00	0	0	0	0.00	0.00	0	0	0	0.00	0.00	2025-12-19 22:24:37	2025-12-19 22:24:38	45.00	0.00	0.00
4	4	1	1	0	100.00	0	0	50.00	0	0	0	0.00	0.00	0	0	0	0.00	0.00	2026-01-03 21:48:39	2026-01-03 21:48:39	75.00	0.00	0.00
1	3	22	10	12	45.45	4	1	11.25	0	0	0	0.00	0.00	0	0	0	0.00	0.00	2025-11-12 20:43:10	2026-02-03 12:22:56	28.35	0.00	0.00
2	6	1	1	0	100.00	0	0	45.00	0	0	0	0.00	0.00	0	0	0	0.00	0.00	2025-11-18 12:10:39	2025-11-18 12:10:40	72.50	0.00	0.00
\.


--
-- Data for Name: purchases; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.purchases (id, user_id, item_name, item_type, price, currency, payment_method, status, transaction_id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: question_history; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.question_history (id, user_id, question_id, question_hash, correct_answer, theme, niveau, created_at, updated_at) FROM stdin;
1	3	faune_ai_516d252b36268ebe31ceb325e69a39f8	516d252b36268ebe31ceb325e69a39f8	Événement de reproduction synchronisée	faune	100	2025-11-04 19:41:58	2025-11-04 19:41:58
2	3	faune_ai_e979b1625e3de27a74d7106b36714338	e979b1625e3de27a74d7106b36714338	Équation de circulation multi-dimensionnelle	faune	100	2025-11-04 19:42:25	2025-11-04 19:42:25
3	3	faune_ai_dad58d8a6772182f32ae201640e5edd1	dad58d8a6772182f32ae201640e5edd1	Bioluminescence symbiotique avec des bactéries lumineuses	faune	100	2025-11-04 19:42:54	2025-11-04 19:42:54
4	3	faune_ai_a7c3cd14803dba4e5f1e75fa4414bdf8	a7c3cd14803dba4e5f1e75fa4414bdf8	Le polymorphisme génétique adaptatif	faune	100	2025-11-04 19:42:59	2025-11-04 19:42:59
5	3	faune_ai_5edcb0f16809bfd1b75fe0730b358aad	5edcb0f16809bfd1b75fe0730b358aad	El Niño côtier	faune	100	2025-11-04 19:43:00	2025-11-04 19:43:00
6	3	faune_ai_1f89305971bc806ae989767f1b380b0a	1f89305971bc806ae989767f1b380b0a	Couplage d'abondance alimentaire terre-mer	faune	100	2025-11-04 19:43:32	2025-11-04 19:43:32
7	3	faune_ai_d796e60d14f3091f729a3df2e026b1c0	d796e60d14f3091f729a3df2e026b1c0	Transfert horizontal de gènes entre espèces marine	faune	100	2025-11-04 19:43:34	2025-11-04 19:43:34
8	3	faune_ai_7df015eb94dbb681752f1392951e481f	7df015eb94dbb681752f1392951e481f	Faux	faune	100	2025-11-04 19:44:03	2025-11-04 19:44:03
9	3	faune_ai_2421288f2693a1febfa0bc33ef41ecb5	2421288f2693a1febfa0bc33ef41ecb5	Écholocation et vocalisations complexes	faune	100	2025-11-04 19:44:25	2025-11-04 19:44:25
10	3	faune_ai_6fa9615cb4cf228a703b5e5162214b8c	6fa9615cb4cf228a703b5e5162214b8c	Capacité à maintenir les cellules souches mésenchymateuses	faune	100	2025-11-04 19:44:28	2025-11-04 19:44:28
11	3	faune_ai_8419541f0117375b31ed4d8caa83b5e0	8419541f0117375b31ed4d8caa83b5e0	Cohérence hydrique à travers le métabolisme des lipides	faune	100	2025-11-04 19:46:12	2025-11-04 19:46:12
12	3	faune_ai_0a561e601b90ea4e18df471eeae4ccd7	0a561e601b90ea4e18df471eeae4ccd7	Pression osmotique dans le réseau mycélien	faune	100	2025-11-04 19:46:43	2025-11-04 19:46:43
13	3	faune_ai_e508941ce820bd37ce8c955e69f71898	e508941ce820bd37ce8c955e69f71898	Vrai	faune	100	2025-11-04 19:46:46	2025-11-04 19:46:46
14	3	faune_ai_f4fd621788057770d1a07fd88f275336	f4fd621788057770d1a07fd88f275336	Endosymbiose bactérienne intra-cellulaire	faune	100	2025-11-04 19:47:07	2025-11-04 19:47:07
15	3	faune_ai_93acc1903624c47450de4fb03e89e60d	93acc1903624c47450de4fb03e89e60d	Réaction à l'empreinte pelliculaire congénitale	faune	100	2025-11-04 19:47:34	2025-11-04 19:47:34
16	3	faune_ai_6e7bd62716d86b4b6f96e2f105b83599	6e7bd62716d86b4b6f96e2f105b83599	Panda	faune	10	2025-11-04 19:48:57	2025-11-04 19:48:57
17	3	faune_ai_8eae0f4a14c4b899a155a62bcfbdbfce	8eae0f4a14c4b899a155a62bcfbdbfce	Caméléon	faune	10	2025-11-04 19:49:20	2025-11-04 19:49:20
18	3	faune_ai_835c4e64e3310fb1229ef3a5f96099b3	835c4e64e3310fb1229ef3a5f96099b3	Chameau	faune	10	2025-11-04 19:49:37	2025-11-04 19:49:37
19	3	faune_ai_8fba407d988aac357cfc7ce9745cd4dc	8fba407d988aac357cfc7ce9745cd4dc	Le lézard	faune	10	2025-11-04 19:50:02	2025-11-04 19:50:02
20	3	faune_ai_98ccc1b21e84b63fd82950746ac238a1	98ccc1b21e84b63fd82950746ac238a1	Papillon	faune	10	2025-11-04 19:50:21	2025-11-04 19:50:21
21	3	faune_ai_d44b195be7fc2fc9be5d62d4036cc678	d44b195be7fc2fc9be5d62d4036cc678	Hérisson	faune	10	2025-11-04 19:50:40	2025-11-04 19:50:40
22	3	faune_ai_20ea675b942778be27de60b719929d1c	20ea675b942778be27de60b719929d1c	Dauphin	faune	10	2025-11-04 19:50:58	2025-11-04 19:50:58
23	3	faune_ai_cb9d277071c4dec4628e5cf732f9942b	cb9d277071c4dec4628e5cf732f9942b	rivières et ruisseaux	faune	10	2025-11-04 19:51:27	2025-11-04 19:51:27
24	3	faune_ai_e932b69ba0bff2ff368c3b71727c5fd2	e932b69ba0bff2ff368c3b71727c5fd2	Narval	faune	10	2025-11-04 19:51:48	2025-11-04 19:51:48
25	3	faune_ai_8c3981c22fd4fe6cefa4de0eff3cc29b	8c3981c22fd4fe6cefa4de0eff3cc29b	Paresseux	faune	10	2025-11-04 19:52:08	2025-11-04 19:52:08
26	3	faune_ai_dcf87677bf78f50f7c6486f1ad63ab1a	dcf87677bf78f50f7c6486f1ad63ab1a	zèbre	faune	10	2025-11-04 19:52:57	2025-11-04 19:52:57
27	3	faune_ai_2f2d09dab6022cb34718ebee3f43ff09	2f2d09dab6022cb34718ebee3f43ff09	Seiche	faune	10	2025-11-04 19:53:13	2025-11-04 19:53:13
28	3	faune_ai_ea4311f72400a695595212597e3409b7	ea4311f72400a695595212597e3409b7	Les savanes	faune	10	2025-11-04 19:53:51	2025-11-04 19:53:51
29	3	faune_ai_c77c85d012ae8e6012d403e4af6ac8b8	c77c85d012ae8e6012d403e4af6ac8b8	Castor	faune	10	2025-11-04 19:54:21	2025-11-04 19:54:21
30	3	faune_ai_247b65a62fdfdcb11407787257a39d23	247b65a62fdfdcb11407787257a39d23	La pieuvre	faune	10	2025-11-04 19:54:45	2025-11-04 19:54:45
31	3	faune_ai_29678b8bdd4337c8c0d7cc154c81ecaa	29678b8bdd4337c8c0d7cc154c81ecaa	Poisson-globe	faune	10	2025-11-04 19:55:04	2025-11-04 19:55:04
32	3	faune_ai_90d3a495364202e9fb5879dea1983075	90d3a495364202e9fb5879dea1983075	Le possum volan	faune	10	2025-11-04 19:55:25	2025-11-04 19:55:25
33	3	faune_ai_82020efd20f103d7c8bb43077c9a2f6c	82020efd20f103d7c8bb43077c9a2f6c	Phasme	faune	10	2025-11-04 19:55:51	2025-11-04 19:55:51
34	3	faune_ai_1ee0278c3678153dd94b26287fa0c8b5	1ee0278c3678153dd94b26287fa0c8b5	Kangourou	faune	10	2025-11-04 19:56:10	2025-11-04 19:56:10
35	3	faune_ai_5f29e683964e7d32465dc58ed1a51ae1	5f29e683964e7d32465dc58ed1a51ae1	La fourmi	faune	10	2025-11-04 19:58:10	2025-11-04 19:58:10
36	3	faune_ai_80a51984cce6525d75d8c7c182102849	80a51984cce6525d75d8c7c182102849	Abeille	faune	10	2025-11-04 19:58:26	2025-11-04 19:58:26
37	3	faune_ai_57c27ecb328c4feb4cc344058286f0f0	57c27ecb328c4feb4cc344058286f0f0	Les cactus	faune	10	2025-11-04 19:58:50	2025-11-04 19:58:50
38	3	faune_ai_139a5b0cec3c653b8b9112217d71962e	139a5b0cec3c653b8b9112217d71962e	Le toucan	faune	10	2025-11-04 19:59:20	2025-11-04 19:59:20
39	3	faune_ai_31f911331808e8daa4147ca7bdc67c4d	31f911331808e8daa4147ca7bdc67c4d	Capacité d'adaptation au stress environnemental	faune	10	2025-11-04 19:59:29	2025-11-04 19:59:29
40	3	faune_ai_9794899d6fe141e6ccdad4c89a311753	9794899d6fe141e6ccdad4c89a311753	Écotone	faune	10	2025-11-04 19:59:57	2025-11-04 19:59:57
41	3	faune_ai_b7c23c751b638c109f9bb25c82e0ad26	b7c23c751b638c109f9bb25c82e0ad26	Papillon monarque	faune	10	2025-11-04 20:00:22	2025-11-04 20:00:22
42	3	faune_ai_dcfdf8068ade3f5a7955adde34adba6f	dcfdf8068ade3f5a7955adde34adba6f	Le saumon	faune	10	2025-11-04 20:00:44	2025-11-04 20:00:44
43	3	faune_ai_03560251ebcaba515a4e8559c56c9e27	03560251ebcaba515a4e8559c56c9e27	tortue	faune	10	2025-11-04 20:01:14	2025-11-04 20:01:14
44	3	general_ai_f59bd148266ec3b4397f4bb5a7adbdd3	f59bd148266ec3b4397f4bb5a7adbdd3	La presse à imprimer	general	10	2025-11-04 20:42:28	2025-11-04 20:42:28
45	3	general_ai_101c493640d05d6baae94f6317309881	101c493640d05d6baae94f6317309881	Estomac	general	10	2025-11-04 20:43:01	2025-11-04 20:43:01
46	3	general_ai_f0880aaed997ed9e1aeab4bae5716484	f0880aaed997ed9e1aeab4bae5716484	Kiwi	general	10	2025-11-04 20:43:20	2025-11-04 20:43:20
47	3	general_ai_455c76c56b320d10a905311608768b08	455c76c56b320d10a905311608768b08	Mexique ancien	general	10	2025-11-04 20:43:42	2025-11-04 20:43:42
48	3	general_ai_b7c127cb1e1117236604018a6ae9b6ab	b7c127cb1e1117236604018a6ae9b6ab	Tomate	general	10	2025-11-04 20:43:59	2025-11-04 20:43:59
49	3	general_ai_acee5776281066bc6d71a16a5e52e898	acee5776281066bc6d71a16a5e52e898	Pomme	general	10	2025-11-04 20:44:17	2025-11-04 20:44:17
50	3	general_ai_4041e2fcb19accd88334a848c088fd49	4041e2fcb19accd88334a848c088fd49	Rouge	general	10	2025-11-04 20:44:41	2025-11-04 20:44:41
51	3	general_ai_7c26bad83d0cd14ab9cd5876879fc764	7c26bad83d0cd14ab9cd5876879fc764	Égypte	general	10	2025-11-04 20:45:01	2025-11-04 20:45:01
52	3	general_ai_d9a2f5df5e639a4b214dc4b44698ced4	d9a2f5df5e639a4b214dc4b44698ced4	Aluminium	general	10	2025-11-04 20:45:29	2025-11-04 20:45:29
53	3	general_ai_ae36a3d95af214a0fc84d00ab2975643	ae36a3d95af214a0fc84d00ab2975643	Banane	general	10	2025-11-04 20:46:56	2025-11-04 20:46:56
54	3	general_ai_4a88e93fc5bf48a80bb11079fda1e84e	4a88e93fc5bf48a80bb11079fda1e84e	Téléphone à clapet	general	10	2025-11-04 20:48:54	2025-11-04 20:48:54
55	3	general_ai_de8981ca628e990eb10f90d6524ddd6d	de8981ca628e990eb10f90d6524ddd6d	Poire	general	10	2025-11-04 20:49:25	2025-11-04 20:49:25
56	3	general_ai_49296168b756ad24f2c7453ce9e1eb56	49296168b756ad24f2c7453ce9e1eb56	le citron	general	10	2025-11-04 20:49:49	2025-11-04 20:49:49
57	3	general_ai_88a48341991fccc8fd9a835063c63b58	88a48341991fccc8fd9a835063c63b58	saxophone	general	10	2025-11-04 20:50:14	2025-11-04 20:50:14
58	3	general_ai_2ad5cb846f2035dd65563fa574555805	2ad5cb846f2035dd65563fa574555805	Australie	general	10	2025-11-04 20:50:42	2025-11-04 20:50:42
59	3	general_ai_89d5554df5f2cd45265d764d3332daaf	89d5554df5f2cd45265d764d3332daaf	Pêche	general	10	2025-11-04 20:50:59	2025-11-04 20:50:59
60	3	general_ai_cfdd2dd9e1a39a0507c8e85b3f47cd9c	cfdd2dd9e1a39a0507c8e85b3f47cd9c	Bernie	general	10	2025-11-04 20:51:18	2025-11-04 20:51:18
61	3	general_ai_1f8e1f659d86dafbff6d22cb21fc814e	1f8e1f659d86dafbff6d22cb21fc814e	Calcaire	general	10	2025-11-04 20:51:49	2025-11-04 20:51:49
62	3	general_ai_1d395f957023c96ec38f52d738baa3c7	1d395f957023c96ec38f52d738baa3c7	Djembe	general	10	2025-11-04 20:52:12	2025-11-04 20:52:12
63	3	general_ai_260fb02bff61b75c0ae506ddb76cbd4b	260fb02bff61b75c0ae506ddb76cbd4b	Le ballet	general	7	2025-11-12 15:53:52	2025-11-12 15:53:52
64	3	general_ai_7b8ebae97f790356cce96830a1506ef4	7b8ebae97f790356cce96830a1506ef4	la datte	general	7	2025-11-12 15:54:30	2025-11-12 15:54:30
65	3	general_ai_40e310857cec24b20ac842380f0729b8	40e310857cec24b20ac842380f0729b8	Fer	general	7	2025-11-12 15:55:11	2025-11-12 15:55:11
66	3	general_ai_0c34fd80014b74b98d84c2fd2a2d6b79	0c34fd80014b74b98d84c2fd2a2d6b79	La pomme	general	7	2025-11-12 15:56:09	2025-11-12 15:56:09
67	3	general_ai_85e84d7a69bdd3d8b35a64b78ec63429	85e84d7a69bdd3d8b35a64b78ec63429	Désert du Sahara	general	7	2025-11-12 15:56:40	2025-11-12 15:56:40
68	3	general_ai_e3063a44018c9887302cf6e697904dc3	e3063a44018c9887302cf6e697904dc3	Mangue	general	7	2025-11-12 15:57:15	2025-11-12 15:57:15
69	3	general_ai_8f278e259c9dc3fcb6e94a32ad4ea897	8f278e259c9dc3fcb6e94a32ad4ea897	portraitiste	general	7	2025-11-12 15:57:58	2025-11-12 15:57:58
70	3	general_ai_0f9d867837e369db35ca925ed734eaee	0f9d867837e369db35ca925ed734eaee	Les congas	general	7	2025-11-12 15:58:45	2025-11-12 15:58:45
71	3	general_ai_5cdf194a5baa944d92e907e6b08afb70	5cdf194a5baa944d92e907e6b08afb70	Saucisse	general	7	2025-11-12 15:59:22	2025-11-12 15:59:22
72	3	general_ai_36ff7540188160bbda9e8ae11f36df5e	36ff7540188160bbda9e8ae11f36df5e	La banane	general	7	2025-11-12 15:59:52	2025-11-12 15:59:52
73	3	general_ai_eb0d2c8b997089be1826b4a3bc6d9c86	eb0d2c8b997089be1826b4a3bc6d9c86	La stratosphère	general	7	2025-11-12 16:02:02	2025-11-12 16:02:02
74	3	general_ai_664ac05c0d7fa6a7a367bbe067b41c60	664ac05c0d7fa6a7a367bbe067b41c60	Seine	general	7	2025-11-12 16:02:32	2025-11-12 16:02:32
75	3	general_ai_1bd98ab31f211dc7a78c4dbc0ee524e4	1bd98ab31f211dc7a78c4dbc0ee524e4	Marées	general	7	2025-11-12 16:03:01	2025-11-12 16:03:01
76	3	general_ai_0e2fd7731124752ad3e28b390e6c4534	0e2fd7731124752ad3e28b390e6c4534	Gouda	general	7	2025-11-12 16:03:25	2025-11-12 16:03:25
77	3	general_ai_71a2f4739ce176583f2227ed83113bef	71a2f4739ce176583f2227ed83113bef	citron	general	7	2025-11-12 16:03:54	2025-11-12 16:03:54
78	3	general_ai_0cb1bd8ab4b03a99a628ca02d55ef643	0cb1bd8ab4b03a99a628ca02d55ef643	Haut-parleur	general	7	2025-11-12 16:04:25	2025-11-12 16:04:25
79	3	general_ai_0815ab621a36fa11c3aa01a870676d67	0815ab621a36fa11c3aa01a870676d67	La guitare	general	7	2025-11-12 16:04:59	2025-11-12 16:04:59
80	3	general_ai_cb469f452242cc45c210c3e792e9cf85	cb469f452242cc45c210c3e792e9cf85	Poulpe	general	7	2025-11-12 16:05:34	2025-11-12 16:05:34
81	3	general_ai_113e8ce74fbd41c5f6e348ee35069946	113e8ce74fbd41c5f6e348ee35069946	Écran LCD	general	7	2025-11-12 16:06:05	2025-11-12 16:06:05
82	3	sciences_ai_eaaa7f8e311cc22351dc52d9e15a9908	eaaa7f8e311cc22351dc52d9e15a9908	CO2	sciences	7	2025-11-12 17:54:54	2025-11-12 17:54:54
83	3	sciences_ai_f01e65eeb0f598dd4712c96d3dbcc7d5	f01e65eeb0f598dd4712c96d3dbcc7d5	Photosynthèse	sciences	7	2025-11-12 17:55:25	2025-11-12 17:55:25
84	3	sciences_ai_a8b379ea92b8e827aa539b2e2600b9ad	a8b379ea92b8e827aa539b2e2600b9ad	Capilarité	sciences	7	2025-11-12 17:55:52	2025-11-12 17:55:52
85	3	sciences_ai_161fe757ecad3c95bccbb23c8facda85	161fe757ecad3c95bccbb23c8facda85	L'évaporation	sciences	7	2025-11-12 17:56:13	2025-11-12 17:56:13
86	3	sciences_ai_15b8a923f6e6c2b7a9e17543932e6a31	15b8a923f6e6c2b7a9e17543932e6a31	Oxygène	sciences	7	2025-11-12 17:56:44	2025-11-12 17:56:44
87	3	sciences_ai_825e8deee16e8684513acd6d5be5a060	825e8deee16e8684513acd6d5be5a060	La germination	sciences	7	2025-11-12 17:57:08	2025-11-12 17:57:08
88	3	sciences_ai_22ba4daec59ab73be0c6e67deaaeb5c9	22ba4daec59ab73be0c6e67deaaeb5c9	Dioxyde de carbone	sciences	7	2025-11-12 17:57:32	2025-11-12 17:57:32
89	3	sciences_ai_9c2c939c340a7beae49e0998b2b28339	9c2c939c340a7beae49e0998b2b28339	Fusion	sciences	7	2025-11-12 17:57:57	2025-11-12 17:57:57
90	3	sciences_ai_38622a05469ab3266c0719e695f283b8	38622a05469ab3266c0719e695f283b8	sa polarité	sciences	7	2025-11-12 17:58:23	2025-11-12 17:58:23
91	3	sciences_ai_f2b5013a31db4f7d8f2c4f4e9ff2aeea	f2b5013a31db4f7d8f2c4f4e9ff2aeea	glucose	sciences	7	2025-11-12 17:58:48	2025-11-12 17:58:48
92	3	sciences_ai_8d210d1306805d4bd32d8c03959611f1	8d210d1306805d4bd32d8c03959611f1	L'effet Coriolis	sciences	7	2025-11-12 18:01:30	2025-11-12 18:01:30
93	3	sciences_ai_878aa1023514b778c13c09d8e5c9752e	878aa1023514b778c13c09d8e5c9752e	Hélium	sciences	7	2025-11-12 18:02:00	2025-11-12 18:02:00
94	3	sciences_ai_64f97eda7e1af5ae40ad5e100bfe8aa2	64f97eda7e1af5ae40ad5e100bfe8aa2	ADN	sciences	7	2025-11-12 18:02:20	2025-11-12 18:02:20
95	3	sciences_ai_92c23c0d4570d243a018aec8c1af3e48	92c23c0d4570d243a018aec8c1af3e48	L'inclinaison de l'axe terrestre	sciences	7	2025-11-12 18:02:48	2025-11-12 18:02:48
96	3	sciences_ai_efe251408aa1d10644120f09fe62a32a	efe251408aa1d10644120f09fe62a32a	électron	sciences	7	2025-11-12 18:02:53	2025-11-12 18:02:53
97	3	sciences_ai_001158a8f18a161e8a47fe1030e733fa	001158a8f18a161e8a47fe1030e733fa	chlorophylle	sciences	7	2025-11-12 18:03:23	2025-11-12 18:03:23
196	3	general_ai_c2218b46cd4e5c8c9cfc5fa1e993aefe	c2218b46cd4e5c8c9cfc5fa1e993aefe	fusée	general	1	2025-11-17 16:11:45	2025-11-17 16:11:45
98	3	sciences_ai_a3235b9b96199a6a7cd35f49ddfb5962	a3235b9b96199a6a7cd35f49ddfb5962	Panneau solaire	sciences	7	2025-11-12 18:03:27	2025-11-12 18:03:27
99	3	sciences_ai_61986bdd2f5ce083d650cfa1f41ec7be	61986bdd2f5ce083d650cfa1f41ec7be	La membrane cellulaire	sciences	7	2025-11-12 18:03:54	2025-11-12 18:03:54
100	3	sciences_ai_195e260319d0adfc5458ef074c9922db	195e260319d0adfc5458ef074c9922db	Peau	sciences	7	2025-11-12 18:04:17	2025-11-12 18:04:17
101	3	general_ai_bcdf6557ad203c08c78b05c0b6e9dbb9	bcdf6557ad203c08c78b05c0b6e9dbb9	France	general	7	2025-11-12 18:28:17	2025-11-12 18:28:17
102	3	general_ai_c18c59729456c54413eb98f2a5845ce4	c18c59729456c54413eb98f2a5845ce4	cornemuse	general	7	2025-11-12 18:28:33	2025-11-12 18:28:33
103	3	general_ai_6e190525a11467eab3de13d2da64f7b2	6e190525a11467eab3de13d2da64f7b2	Céleri	general	7	2025-11-12 18:28:43	2025-11-12 18:28:43
104	3	general_ai_291013733c5021d104f03ab1a13c7f3e	291013733c5021d104f03ab1a13c7f3e	piano	general	7	2025-11-12 18:29:06	2025-11-12 18:29:06
105	3	general_ai_115493b1971988b79866d80934694bb3	115493b1971988b79866d80934694bb3	Eau	general	7	2025-11-12 18:29:30	2025-11-12 18:29:30
106	3	general_ai_7d2b2ee63d7bc6f5d5a46ff5ee22784b	7d2b2ee63d7bc6f5d5a46ff5ee22784b	Brais­er	general	7	2025-11-12 18:29:44	2025-11-12 18:29:44
107	3	general_ai_342201d9d3a3a12424651ec6e615da39	342201d9d3a3a12424651ec6e615da39	Aurores boréales	general	7	2025-11-12 18:30:11	2025-11-12 18:30:11
108	3	general_ai_88d05d4ebf824823b3e43555bba0aab2	88d05d4ebf824823b3e43555bba0aab2	Grèce	general	7	2025-11-12 18:30:33	2025-11-12 18:30:33
109	3	general_ai_1e88c17a7607cd6880c106636f7eedb0	1e88c17a7607cd6880c106636f7eedb0	Nouvelle-Zélande	general	7	2025-11-12 18:30:52	2025-11-12 18:30:52
110	3	general_ai_56244f5bedbca6ee17951b797385da82	56244f5bedbca6ee17951b797385da82	Prune	general	7	2025-11-12 18:31:20	2025-11-12 18:31:20
111	3	general_ai_b5b8b99f70c0f419315aac82b5fc7308	b5b8b99f70c0f419315aac82b5fc7308	pseudomonas aeruginosa	general	57	2025-11-12 20:33:17	2025-11-12 20:33:17
112	3	general_ai_eefc52c9bc2b190830d54e53a60539aa	eefc52c9bc2b190830d54e53a60539aa	les misérables	general	57	2025-11-12 20:33:57	2025-11-12 20:33:57
113	3	general_ai_fc4cdfb0886260b52c496e13cd440a4b	fc4cdfb0886260b52c496e13cd440a4b	kintsugi	general	57	2025-11-12 20:34:01	2025-11-12 20:34:01
114	3	general_ai_e31d3de27c1c12fc88e60c0808f588f3	e31d3de27c1c12fc88e60c0808f588f3	george orwell	general	57	2025-11-12 20:34:03	2025-11-12 20:34:03
115	3	general_ai_eb21b358e08072c980b73057607cdd69	eb21b358e08072c980b73057607cdd69	une dévastation des populations d'oiseaux marins et une réduction de la biodiversité côtière	general	57	2025-11-12 20:34:44	2025-11-12 20:34:44
116	3	general_ai_74db0ade8215edec76abede59c028f35	74db0ade8215edec76abede59c028f35	aristote	general	57	2025-11-12 20:35:14	2025-11-12 20:35:14
117	3	general_ai_806ad25d7074488f870c82ee850aa846	806ad25d7074488f870c82ee850aa846	le congrès de vienne	general	57	2025-11-12 20:35:38	2025-11-12 20:35:38
118	3	general_ai_ab3f0585ce79bf09f3772776dab40a99	ab3f0585ce79bf09f3772776dab40a99	john rawls	general	57	2025-11-12 20:36:12	2025-11-12 20:36:12
119	3	general_ai_fd21b792a791ebfb065c54551bac8344	fd21b792a791ebfb065c54551bac8344	protocole de kyoto	general	57	2025-11-12 20:36:37	2025-11-12 20:36:37
120	3	general_ai_b674ada3c5fb75d5b0cf7c333d64fea9	b674ada3c5fb75d5b0cf7c333d64fea9	traité d'utrecht	general	57	2025-11-12 20:37:03	2025-11-12 20:37:03
121	3	general_ai_747be282defd05901368b47b94173c38	747be282defd05901368b47b94173c38	jean-paul sartre	general	57	2025-11-12 20:39:26	2025-11-12 20:39:26
122	3	general_ai_b84ce8852961108d69f2a47714b05b6b	b84ce8852961108d69f2a47714b05b6b	michel foucault	general	57	2025-11-12 20:39:51	2025-11-12 20:39:51
123	3	general_ai_3956037a4e470cf24323d376cd5d20fa	3956037a4e470cf24323d376cd5d20fa	l'éclipse solaire totale	general	57	2025-11-12 20:40:19	2025-11-12 20:40:19
124	3	general_ai_64211da713312addcc9facd301e08a01	64211da713312addcc9facd301e08a01	traité sur la non-prolifération des armes nucléaires	general	57	2025-11-12 20:40:48	2025-11-12 20:40:48
125	3	general_ai_792c05a2e97a7a74358073e79197e41f	792c05a2e97a7a74358073e79197e41f	albert einstein	general	57	2025-11-12 20:40:52	2025-11-12 20:40:52
126	3	general_ai_37906f8bb6a0bb577c8b1e3cf74787df	37906f8bb6a0bb577c8b1e3cf74787df	henri-decaisne	general	57	2025-11-12 20:41:18	2025-11-12 20:41:18
127	3	general_ai_daef00e8c02735f6a33b026e797184b3	daef00e8c02735f6a33b026e797184b3	claude monet	general	57	2025-11-12 20:41:42	2025-11-12 20:41:42
128	3	general_ai_f1fbfdee20849f03ce1a1c9327f2b77e	f1fbfdee20849f03ce1a1c9327f2b77e	jules verne	general	57	2025-11-12 20:42:12	2025-11-12 20:42:12
129	3	general_ai_0d8e3dc2fbb8ee117d39c3a65dda804d	0d8e3dc2fbb8ee117d39c3a65dda804d	svante arrhenius	general	57	2025-11-12 20:42:43	2025-11-12 20:42:43
130	3	general_ai_387893b54abe3bb2bd68d0018a1db8f0	387893b54abe3bb2bd68d0018a1db8f0	papaye	general	14	2025-11-12 20:58:49	2025-11-12 20:58:49
131	3	general_ai_68c454c5791549264594439541dfa47c	68c454c5791549264594439541dfa47c	francisco de orellana	general	14	2025-11-12 20:59:07	2025-11-12 20:59:07
132	3	general_ai_2fd7cc9510d125455f7a7d3f4a4c0121	2fd7cc9510d125455f7a7d3f4a4c0121	norvège	general	14	2025-11-12 20:59:44	2025-11-12 20:59:44
133	3	general_ai_12cc7f8f2f81ea34a287800f235d773e	12cc7f8f2f81ea34a287800f235d773e	avocat	general	14	2025-11-12 21:00:14	2025-11-12 21:00:14
134	3	general_ai_5892051d1161d047ed93af2b280ab1b2	5892051d1161d047ed93af2b280ab1b2	la grande muraille	general	14	2025-11-12 21:00:38	2025-11-12 21:00:38
135	3	general_ai_cb64ea1e91e08d20a72a5008c709f524	cb64ea1e91e08d20a72a5008c709f524	Éthiopie	general	14	2025-11-12 21:01:13	2025-11-12 21:01:13
136	3	general_ai_56528cdd3262143ced62446c345486d7	56528cdd3262143ced62446c345486d7	téléscope	general	14	2025-11-12 21:01:39	2025-11-12 21:01:39
137	3	general_ai_a09e3ed27f2ecc02cda782607b6456f3	a09e3ed27f2ecc02cda782607b6456f3	tristan tzara	general	14	2025-11-12 21:02:09	2025-11-12 21:02:09
138	3	general_ai_2219bb7950fa910260fdebb9619a1e4d	2219bb7950fa910260fdebb9619a1e4d	chine	general	14	2025-11-12 21:02:39	2025-11-12 21:02:39
139	3	general_ai_554a30f6d15459c6a38c988eeb1af913	554a30f6d15459c6a38c988eeb1af913	arthur rimbaud	general	14	2025-11-12 21:03:04	2025-11-12 21:03:04
140	3	general_ai_3ca37bdcc8132334efc5623786dcb347	3ca37bdcc8132334efc5623786dcb347	cubisme	general	14	2025-11-12 21:04:43	2025-11-12 21:04:43
141	3	general_ai_5f5974b67faa46121901d3a1df527983	5f5974b67faa46121901d3a1df527983	maïs	general	14	2025-11-12 21:05:05	2025-11-12 21:05:05
142	3	general_ai_c5cf68da9fdd0b9630c9d7f6f53198cd	c5cf68da9fdd0b9630c9d7f6f53198cd	béla bartók	general	14	2025-11-12 21:05:27	2025-11-12 21:05:27
143	3	general_ai_4415d83d1b46b2b16f786396b63ddb23	4415d83d1b46b2b16f786396b63ddb23	tanuki	general	14	2025-11-12 21:06:03	2025-11-12 21:06:03
144	3	general_ai_80ecad0379a82211639e62338e4981ee	80ecad0379a82211639e62338e4981ee	chocolat	general	14	2025-11-12 21:06:26	2025-11-12 21:06:26
145	3	general_ai_d3f6143e58d425442fc42a1bd69dbafd	d3f6143e58d425442fc42a1bd69dbafd	les floralies	general	14	2025-11-12 21:06:47	2025-11-12 21:06:47
146	3	general_ai_8fc82099e88af5727431d264674e0936	8fc82099e88af5727431d264674e0936	leonard de vinci	general	14	2025-11-12 21:07:16	2025-11-12 21:07:16
147	3	general_ai_a522532ec5cb253dfaab3d37d74a5edb	a522532ec5cb253dfaab3d37d74a5edb	proxima centauri	general	14	2025-11-12 21:07:37	2025-11-12 21:07:37
148	3	general_ai_eced1ee88266d8e6704e7bfae73b7cae	eced1ee88266d8e6704e7bfae73b7cae	carbone	general	14	2025-11-12 21:08:03	2025-11-12 21:08:03
149	3	faune_ai_d1fd538865c6523371b039cf207f7676	d1fd538865c6523371b039cf207f7676	le mangouste	faune	17	2025-11-13 17:50:52	2025-11-13 17:50:52
150	3	faune_ai_b7deac05f39681de07ec7fc2d4e5016e	b7deac05f39681de07ec7fc2d4e5016e	le dauphin	faune	17	2025-11-13 17:51:26	2025-11-13 17:51:26
151	3	faune_ai_d8f0f0d34189acb340a2bd1db6cf69a9	d8f0f0d34189acb340a2bd1db6cf69a9	la méduse	faune	17	2025-11-13 17:51:48	2025-11-13 17:51:48
152	3	faune_ai_41c4682085c5f0520957398cd4a82da5	41c4682085c5f0520957398cd4a82da5	le mollusque brisé	faune	17	2025-11-13 17:52:22	2025-11-13 17:52:22
153	3	faune_ai_caa5eef04130c9d847a6b3cf404feca0	caa5eef04130c9d847a6b3cf404feca0	lamantin	faune	17	2025-11-13 17:52:48	2025-11-13 17:52:48
154	3	faune_ai_6cca41fd983b64057ade3d9657d8eb1e	6cca41fd983b64057ade3d9657d8eb1e	le caméléon	faune	17	2025-11-13 17:53:17	2025-11-13 17:53:17
155	3	faune_ai_033ef158e1ddf51ac035a081da2f6239	033ef158e1ddf51ac035a081da2f6239	la formation des glaciers	faune	17	2025-11-13 17:53:38	2025-11-13 17:53:38
156	3	faune_ai_bf9ce859468882f67fbd7869d2a7f6ef	bf9ce859468882f67fbd7869d2a7f6ef	la termite	faune	17	2025-11-13 17:54:04	2025-11-13 17:54:04
157	3	faune_ai_8db6ec12469cb21576c54552ec3c9d4f	8db6ec12469cb21576c54552ec3c9d4f	lianaise	faune	17	2025-11-13 17:54:33	2025-11-13 17:54:33
158	3	faune_ai_deacc799e8fd6184e64ead78c256422a	deacc799e8fd6184e64ead78c256422a	le perroquet	faune	17	2025-11-13 17:55:05	2025-11-13 17:55:05
159	3	faune_ai_f3f110507a5f86453ee45e0ca6109b5e	f3f110507a5f86453ee45e0ca6109b5e	le marsupilami	faune	17	2025-11-13 18:00:44	2025-11-13 18:00:44
160	3	faune_ai_16654373fd7e3698b81e5bff2e0cbcfa	16654373fd7e3698b81e5bff2e0cbcfa	le sens de l'azimut	faune	17	2025-11-13 18:01:07	2025-11-13 18:01:07
161	3	faune_ai_9ae2adeeb76b77cb39465aee098785b8	9ae2adeeb76b77cb39465aee098785b8	le serpent liane	faune	17	2025-11-13 18:01:48	2025-11-13 18:01:48
162	3	faune_ai_498e6b2e3879ad62668f2b16581bf83c	498e6b2e3879ad62668f2b16581bf83c	mimétisme diurne	faune	17	2025-11-13 18:02:15	2025-11-13 18:02:15
163	3	faune_ai_f6f5da82d889968794b18daf090f72ce	f6f5da82d889968794b18daf090f72ce	la danse nuptiale	faune	17	2025-11-13 18:02:48	2025-11-13 18:02:48
164	3	faune_ai_94c2fbc7a2278a368a586fbd5bb6cfcd	94c2fbc7a2278a368a586fbd5bb6cfcd	le changement de temperatures saisonnier	faune	17	2025-11-13 18:03:30	2025-11-13 18:03:30
165	3	faune_ai_d2d8b7839fb80b71de314285b0a3794a	d2d8b7839fb80b71de314285b0a3794a	niche écologique	faune	17	2025-11-13 18:04:12	2025-11-13 18:04:12
166	3	faune_ai_e59f8286a1fb5a0a1010ee036005742d	e59f8286a1fb5a0a1010ee036005742d	loutre de mer	faune	17	2025-11-13 18:04:41	2025-11-13 18:04:41
167	3	faune_ai_0acc2728b4fac4ad487abd286c83250a	0acc2728b4fac4ad487abd286c83250a	le paon	faune	17	2025-11-13 18:05:10	2025-11-13 18:05:10
168	8	general_ai_d0e313c78bdf59f7db448a014496ae0a	d0e313c78bdf59f7db448a014496ae0a	faux	general	1	2025-11-17 15:03:38	2025-11-17 15:03:38
169	8	general_ai_fe434af7c6ad62ab596688ec3a771f85	fe434af7c6ad62ab596688ec3a771f85	cacao	general	1	2025-11-17 15:04:20	2025-11-17 15:04:20
170	8	general_ai_61e692b48c03929cdd869bd6cbd20f3f	61e692b48c03929cdd869bd6cbd20f3f	plantain	general	1	2025-11-17 15:05:03	2025-11-17 15:05:03
171	8	general_ai_a152c6232484f457e1df2db28ad104a8	a152c6232484f457e1df2db28ad104a8	fraise	general	1	2025-11-17 15:05:47	2025-11-17 15:05:47
172	8	general_ai_611b6727f6eefe05ecd348c570625aec	611b6727f6eefe05ecd348c570625aec	mangue	general	1	2025-11-17 15:06:21	2025-11-17 15:06:21
173	8	general_ai_5c3e449dbbc4832f0d88a0672d14c3f5	5c3e449dbbc4832f0d88a0672d14c3f5	vrai	general	1	2025-11-17 15:06:24	2025-11-17 15:06:24
174	8	general_ai_ef40a07a26488c6307d7bd32e5c8d5b5	ef40a07a26488c6307d7bd32e5c8d5b5	pomme	general	1	2025-11-17 15:06:53	2025-11-17 15:06:53
175	8	general_ai_2a75524a265b65ee8a821134bf12ac52	2a75524a265b65ee8a821134bf12ac52	taro	general	1	2025-11-17 15:07:21	2025-11-17 15:07:21
176	8	general_ai_9d8e467571c0e287af0be719a978316b	9d8e467571c0e287af0be719a978316b	grillon	general	1	2025-11-17 15:07:44	2025-11-17 15:07:44
177	8	general_ai_35831d421519e153e6ce1f296b8c0fda	35831d421519e153e6ce1f296b8c0fda	janvier	general	1	2025-11-17 15:08:09	2025-11-17 15:08:09
178	8	general_ai_9c10da7efe4d347873212f01e0c0a4a4	9c10da7efe4d347873212f01e0c0a4a4	5	general	1	2025-11-17 15:13:41	2025-11-17 15:13:41
179	8	general_ai_53697af80baf049644bca6a98a954d39	53697af80baf049644bca6a98a954d39	gustave flaubert	general	1	2025-11-17 15:13:43	2025-11-17 15:13:43
180	8	general_ai_571598197c5f51ede4f0e91f92f7cb06	571598197c5f51ede4f0e91f92f7cb06	guitare	general	1	2025-11-17 15:14:20	2025-11-17 15:14:20
181	8	general_ai_8c16c7166579326d7fea2464a939e3d7	8c16c7166579326d7fea2464a939e3d7	trompette	general	1	2025-11-17 15:14:23	2025-11-17 15:14:23
182	8	general_ai_1d420b039cc75c8e4a817f9de1dc480b	1d420b039cc75c8e4a817f9de1dc480b	framboise	general	1	2025-11-17 15:14:59	2025-11-17 15:14:59
183	8	general_ai_0e1fa765aaaaf401255277e53d4cf5e4	0e1fa765aaaaf401255277e53d4cf5e4	Égypte	general	1	2025-11-17 15:15:05	2025-11-17 15:15:05
184	8	general_ai_613d3c9db839be7a39f7d0358f12d4cb	613d3c9db839be7a39f7d0358f12d4cb	océan pacifique	general	1	2025-11-17 15:15:52	2025-11-17 15:15:52
185	8	general_ai_61c9ab606a715fdbaab420795bebd665	61c9ab606a715fdbaab420795bebd665	carotte	general	1	2025-11-17 15:15:54	2025-11-17 15:15:54
186	8	general_ai_1c3881697ffbd025eeb3f97e2cab5faa	1c3881697ffbd025eeb3f97e2cab5faa	abeille	general	1	2025-11-17 15:16:23	2025-11-17 15:16:23
187	8	general_ai_0ae258772b161c765a2f16312a0dd78e	0ae258772b161c765a2f16312a0dd78e	kiwi	general	1	2025-11-17 15:17:21	2025-11-17 15:17:21
188	3	general_ai_ddd23d1979dbca0df087d896ea6fb597	ddd23d1979dbca0df087d896ea6fb597	solstice d'été	general	1	2025-11-17 16:08:07	2025-11-17 16:08:07
189	3	general_ai_a4419399e65cb1cd605896efdcf824f5	a4419399e65cb1cd605896efdcf824f5	aubergine	general	1	2025-11-17 16:08:30	2025-11-17 16:08:30
190	3	general_ai_393b241bf5c495e12b8ac5aceae9d0ff	393b241bf5c495e12b8ac5aceae9d0ff	piper nigrum	general	1	2025-11-17 16:09:01	2025-11-17 16:09:01
191	3	general_ai_4ee40fb89336a07a4115025c1c45d967	4ee40fb89336a07a4115025c1c45d967	farine t45	general	1	2025-11-17 16:09:45	2025-11-17 16:09:45
192	3	general_ai_2c4445406b707c48a742c53c08822da7	2c4445406b707c48a742c53c08822da7	orange	general	1	2025-11-17 16:10:17	2025-11-17 16:10:17
193	3	general_ai_6bc82c1e35515e451259b3130c665c3a	6bc82c1e35515e451259b3130c665c3a	arthur conan doyle	general	1	2025-11-17 16:10:46	2025-11-17 16:10:46
194	3	general_ai_fdb45574190bdc50703a8c9e17a501f8	fdb45574190bdc50703a8c9e17a501f8	violon	general	1	2025-11-17 16:11:15	2025-11-17 16:11:15
195	3	general_ai_288da688b41a7f01febdc2a9eac930fa	288da688b41a7f01febdc2a9eac930fa	espagne	general	1	2025-11-17 16:11:16	2025-11-17 16:11:16
197	3	general_ai_1d9e76e6938af68ef0fa4bd9349e310e	1d9e76e6938af68ef0fa4bd9349e310e	patate douce	general	1	2025-11-17 16:12:32	2025-11-17 16:12:32
198	3	general_ai_d34f4af93e134694241e66c45b426129	d34f4af93e134694241e66c45b426129	cuivre	general	1	2025-11-17 18:33:20	2025-11-17 18:33:20
199	3	general_ai_c9fce135b3a31a75cd421e922c30b056	c9fce135b3a31a75cd421e922c30b056	la rose	general	1	2025-11-17 18:34:01	2025-11-17 18:34:01
200	3	general_ai_0d5a3666d2494ff91b628cb390082959	0d5a3666d2494ff91b628cb390082959	l'or	general	1	2025-11-17 18:34:39	2025-11-17 18:34:39
201	3	general_ai_cc37a55076e5795a6f99f365c7473e14	cc37a55076e5795a6f99f365c7473e14	la cerise	general	1	2025-11-17 18:38:36	2025-11-17 18:38:36
202	3	general_ai_b6ddb2440caa88c98e14daf5acdb63e2	b6ddb2440caa88c98e14daf5acdb63e2	fraise	general	1	2025-11-17 18:39:10	2025-11-17 18:39:10
203	3	general_ai_712c2f0cd336ecfa501f1ec4be479851	712c2f0cd336ecfa501f1ec4be479851	sang	general	1	2025-11-17 18:39:38	2025-11-17 18:39:38
204	3	general_ai_31dafcad6a78d402d879c26e499639b1	31dafcad6a78d402d879c26e499639b1	poivron	general	1	2025-11-17 18:39:59	2025-11-17 18:39:59
205	3	general_ai_ab05a0db926fe2ca4df2e345a09babd4	ab05a0db926fe2ca4df2e345a09babd4	la théorie de la relativité d'einstein	general	1	2025-11-17 18:40:35	2025-11-17 18:40:35
206	3	general_ai_411e756d23d72d63f56ff880b57098df	411e756d23d72d63f56ff880b57098df	ananas	general	1	2025-11-17 18:41:21	2025-11-17 18:41:21
207	3	general_ai_9f856ab615bdc36f1475803d43753509	9f856ab615bdc36f1475803d43753509	la lune	general	1	2025-11-17 18:41:57	2025-11-17 18:41:57
208	3	general_ai_1c758bb7c939b8c72c0dae5239d5db3b	1c758bb7c939b8c72c0dae5239d5db3b	bacalhau	general	1	2025-11-17 18:42:19	2025-11-17 18:42:19
209	3	general_ai_bc201e2a81025a34ad8cbb560ec563fe	bc201e2a81025a34ad8cbb560ec563fe	argentine	general	1	2025-11-17 18:42:51	2025-11-17 18:42:51
210	3	general_ai_fe336eb146be4f4fc85f0ed8d3c0b3f1	fe336eb146be4f4fc85f0ed8d3c0b3f1	kiwis	general	1	2025-11-17 18:43:15	2025-11-17 18:43:15
211	3	general_ai_4fbf3bbe48fc22a14c6cf52bbcefb958	4fbf3bbe48fc22a14c6cf52bbcefb958	pastèque	general	1	2025-11-17 19:33:06	2025-11-17 19:33:06
212	3	general_ai_66bd4fa0bf0afbbf92a901e99ddc3354	66bd4fa0bf0afbbf92a901e99ddc3354	m Глаго́ль	general	1	2025-11-17 19:33:45	2025-11-17 19:33:45
213	3	general_ai_f3c8859cd37e973cb9bf1427ac81eea4	f3c8859cd37e973cb9bf1427ac81eea4	parce qu'elles sont formées à partir de facteurs génétiques et environnementaux individuels	general	1	2025-11-17 19:33:53	2025-11-17 19:33:53
214	3	general_ai_0c8ce755b694d076bb700a9d9d102edc	0c8ce755b694d076bb700a9d9d102edc	bleu	general	1	2025-11-17 19:34:24	2025-11-17 19:34:24
215	3	general_ai_3a8bf6c9d0ef0b6535aafd3992d35b31	3a8bf6c9d0ef0b6535aafd3992d35b31	hergé	general	1	2025-11-17 19:34:55	2025-11-17 19:34:55
216	3	general_ai_94851a7363285bc8807793308e914b75	94851a7363285bc8807793308e914b75	girouette	general	1	2025-11-17 19:35:34	2025-11-17 19:35:34
217	3	general_ai_63cea1e7c5de1558f4b8adaa68c0fe34	63cea1e7c5de1558f4b8adaa68c0fe34	cervelle d'agneau	general	1	2025-11-17 19:36:14	2025-11-17 19:36:14
218	3	general_ai_9a1317ffb8dc2311db5d16a00b801535	9a1317ffb8dc2311db5d16a00b801535	grenade	general	1	2025-11-17 19:36:53	2025-11-17 19:36:53
219	3	general_ai_5116daad23582b1e8ea9ea1f60fd7982	5116daad23582b1e8ea9ea1f60fd7982	nashi	general	1	2025-11-17 19:37:29	2025-11-17 19:37:29
220	3	general_ai_156bfb91063213dcfe5513978a27d9c7	156bfb91063213dcfe5513978a27d9c7	château de chambord	general	1	2025-11-17 19:38:05	2025-11-17 19:38:05
221	3	general_ai_95f8b9bc4274b8eb69017277994bb5bd	95f8b9bc4274b8eb69017277994bb5bd	vénus	general	1	2025-11-17 20:13:58	2025-11-17 20:13:58
222	3	general_ai_eaf534de16a6af1db22e09fa214395b6	eaf534de16a6af1db22e09fa214395b6	rein	general	1	2025-11-17 20:14:20	2025-11-17 20:14:20
223	3	general_ai_85a263e279fa18210a93ddb5802a4876	85a263e279fa18210a93ddb5802a4876	méthane	general	1	2025-11-17 20:14:49	2025-11-17 20:14:49
224	3	general_ai_43b1ec0a6c6bf6150436abf105b95897	43b1ec0a6c6bf6150436abf105b95897	la papaye	general	1	2025-11-17 20:15:20	2025-11-17 20:15:20
225	3	general_ai_6c0f4717655cbbfece393b3c3d44c13f	6c0f4717655cbbfece393b3c3d44c13f	botswana	general	1	2025-11-17 20:15:51	2025-11-17 20:15:51
226	3	general_ai_46990e709a730a141ee686f33be10bfb	46990e709a730a141ee686f33be10bfb	la fraise	general	1	2025-11-17 20:16:24	2025-11-17 20:16:24
227	3	general_ai_eeccdb5ea5d73193f907ffe9a9fd60c3	eeccdb5ea5d73193f907ffe9a9fd60c3	le cœur	general	1	2025-11-17 20:16:50	2025-11-17 20:16:50
228	3	general_ai_006909508aad93b1416401696c518995	006909508aad93b1416401696c518995	la boussole	general	1	2025-11-17 20:16:55	2025-11-17 20:16:55
229	3	general_ai_e0d446adc53236443a44adb24faf068d	e0d446adc53236443a44adb24faf068d	pamplemousse	general	1	2025-11-17 20:17:25	2025-11-17 20:17:25
230	3	general_ai_19eda30f2c76ffb938a66d802310a4a2	19eda30f2c76ffb938a66d802310a4a2	guitare	general	1	2025-11-17 20:18:02	2025-11-17 20:18:02
231	3	general_ai_04826de40ca1433a9a6f00642209e219	04826de40ca1433a9a6f00642209e219	l'ail	general	1	2025-11-17 21:09:28	2025-11-17 21:09:28
232	3	general_ai_cbd3c0eb24c4981b3eab0c79c4c53a47	cbd3c0eb24c4981b3eab0c79c4c53a47	laszlo biro	general	1	2025-11-17 21:10:09	2025-11-17 21:10:09
233	3	general_ai_73de75627d200b56190d6c6f0178e354	73de75627d200b56190d6c6f0178e354	mandarine	general	1	2025-11-17 21:10:49	2025-11-17 21:10:49
234	3	general_ai_e7507559e1eedf7e3ae9ac14bae507bd	e7507559e1eedf7e3ae9ac14bae507bd	les bourgeons gustatifs	general	1	2025-11-17 21:11:19	2025-11-17 21:11:19
235	3	general_ai_058b34227c9f61564399b7ce2bb48f42	058b34227c9f61564399b7ce2bb48f42	abricot	general	1	2025-11-17 21:12:10	2025-11-17 21:12:10
236	3	general_ai_f7b43fddd40f7d0a864d4204accf5979	f7b43fddd40f7d0a864d4204accf5979	cannelle	general	1	2025-11-17 21:12:52	2025-11-17 21:12:52
237	3	general_ai_01e1c6539caa28513242f8501a157632	01e1c6539caa28513242f8501a157632	flûte	general	1	2025-11-17 21:12:54	2025-11-17 21:12:54
238	3	general_ai_db025b62365fdd06af68c79ab77702fe	db025b62365fdd06af68c79ab77702fe	medlar	general	1	2025-11-17 21:13:29	2025-11-17 21:13:29
239	3	general_ai_648fb5493a57d93e4d0253fa1c5d00c9	648fb5493a57d93e4d0253fa1c5d00c9	citrouille	general	1	2025-11-17 21:14:03	2025-11-17 21:14:03
240	3	general_ai_9399c37693eb83a2f08f4a0e65220d8c	9399c37693eb83a2f08f4a0e65220d8c	carnaval	general	1	2025-11-17 21:14:39	2025-11-17 21:14:39
241	6	faune_ai_b4dcc845e33649f99228e0f562cd0465	b4dcc845e33649f99228e0f562cd0465	grillon	faune	1	2025-11-18 11:41:23	2025-11-18 11:41:23
242	6	faune_ai_6e9c3fb840e0015e0629b5246cfa1415	6e9c3fb840e0015e0629b5246cfa1415	papillon	faune	1	2025-11-18 11:41:44	2025-11-18 11:41:44
243	6	faune_ai_ecb319d7ccbe32c7603aa2f3e99f890c	ecb319d7ccbe32c7603aa2f3e99f890c	abeille	faune	1	2025-11-18 11:42:09	2025-11-18 11:42:09
244	6	faune_ai_9fd5d7ddf8bec87113fae4f7d3cacee6	9fd5d7ddf8bec87113fae4f7d3cacee6	poulpe	faune	1	2025-11-18 11:42:30	2025-11-18 11:42:30
245	6	faune_ai_6d8414230afb06679457f0ae4c329d64	6d8414230afb06679457f0ae4c329d64	castor	faune	1	2025-11-18 11:42:57	2025-11-18 11:42:57
246	6	faune_ai_68aaf78c8f4fc5c5d33ea99beb02bb29	68aaf78c8f4fc5c5d33ea99beb02bb29	safran	faune	1	2025-11-18 11:43:37	2025-11-18 11:43:37
247	6	faune_ai_7858e14f3a91534239b9380eeeffa1e6	7858e14f3a91534239b9380eeeffa1e6	érable	faune	1	2025-11-18 11:44:14	2025-11-18 11:44:14
248	6	faune_ai_f34fef42a98b1a8644aec8173257bcfe	f34fef42a98b1a8644aec8173257bcfe	hermine	faune	1	2025-11-18 11:44:50	2025-11-18 11:44:50
249	6	faune_ai_8ef2135870dded4eda2bcef2c1120ec7	8ef2135870dded4eda2bcef2c1120ec7	vrai	faune	1	2025-11-18 11:45:27	2025-11-18 11:45:27
250	6	faune_ai_f1b6971c267d9f84cde989aa70ff4bba	f1b6971c267d9f84cde989aa70ff4bba	faux	faune	1	2025-11-18 11:46:04	2025-11-18 11:46:04
251	6	faune_ai_38d96d3bdf89b2f55acbe6c01b52dd35	38d96d3bdf89b2f55acbe6c01b52dd35	hirondelle	faune	1	2025-11-18 12:05:41	2025-11-18 12:05:41
252	6	faune_ai_75d643a83098e87a7960082bf9e79b79	75d643a83098e87a7960082bf9e79b79	vache	faune	1	2025-11-18 12:06:20	2025-11-18 12:06:20
253	6	faune_ai_1bccb4c446c95a7f312560be003800b8	1bccb4c446c95a7f312560be003800b8	tortue	faune	1	2025-11-18 12:06:57	2025-11-18 12:06:57
254	6	faune_ai_2bc6472b6b3fb5560297f75be1df8871	2bc6472b6b3fb5560297f75be1df8871	caméléon	faune	1	2025-11-18 12:07:22	2025-11-18 12:07:22
255	6	faune_ai_f13e5c309ed9fcbabe0c2d1d92a2825b	f13e5c309ed9fcbabe0c2d1d92a2825b	baleine	faune	1	2025-11-18 12:07:48	2025-11-18 12:07:48
256	6	faune_ai_80f105ea3ae7c4a07d4bebd7236b1335	80f105ea3ae7c4a07d4bebd7236b1335	lapin	faune	1	2025-11-18 12:08:12	2025-11-18 12:08:12
257	6	faune_ai_0c7c015570fac10ab9b3ad5fcee67428	0c7c015570fac10ab9b3ad5fcee67428	serpent à sonnette	faune	1	2025-11-18 12:08:35	2025-11-18 12:08:35
258	6	faune_ai_edc1047e26d9974967014c375a5e2c66	edc1047e26d9974967014c375a5e2c66	araignée	faune	1	2025-11-18 12:09:10	2025-11-18 12:09:10
259	6	faune_ai_e5e49fc7e47d96b4bc6821e9822f5cd7	e5e49fc7e47d96b4bc6821e9822f5cd7	éléphant	faune	1	2025-11-18 12:09:44	2025-11-18 12:09:44
260	6	faune_ai_dd3fde2ddcca97d1a91f7f911e016fdc	dd3fde2ddcca97d1a91f7f911e016fdc	grenouille	faune	1	2025-11-18 12:10:02	2025-11-18 12:10:02
261	3	faune_ai_02d7d04b292bfe9aaa07fafb1a61dbfe	02d7d04b292bfe9aaa07fafb1a61dbfe	dragon de komodo	faune	9	2025-11-18 17:16:11	2025-11-18 17:16:11
262	3	faune_ai_83a58992765b3f3175623d38cc1c84ac	83a58992765b3f3175623d38cc1c84ac	le colugo	faune	9	2025-11-18 17:16:54	2025-11-18 17:16:54
263	3	faune_ai_deae00b517e37b08efd3bcf457351d09	deae00b517e37b08efd3bcf457351d09	le mouflon	faune	9	2025-11-18 17:17:37	2025-11-18 17:17:37
264	3	faune_ai_c62632b35696e0314f36d9ecfffe121e	c62632b35696e0314f36d9ecfffe121e	syren	faune	9	2025-11-18 17:18:13	2025-11-18 17:18:13
265	3	faune_ai_bf8cb3269bfd31ae72e5b78f74f7e418	bf8cb3269bfd31ae72e5b78f74f7e418	le zèbre	faune	9	2025-11-18 17:18:51	2025-11-18 17:18:51
266	3	faune_ai_8a77d6ce24a455ebe008a182b25dbf8d	8a77d6ce24a455ebe008a182b25dbf8d	l’échidné	faune	9	2025-11-18 17:19:22	2025-11-18 17:19:22
267	3	faune_ai_be3a6112219ac6b783967b59eb8c1c1d	be3a6112219ac6b783967b59eb8c1c1d	la grenouille	faune	9	2025-11-18 17:19:53	2025-11-18 17:19:53
268	3	faune_ai_b452a4ef284c1739f7b0b272471b68ff	b452a4ef284c1739f7b0b272471b68ff	le lamantin	faune	9	2025-11-18 17:20:20	2025-11-18 17:20:20
269	3	faune_ai_dad777d8ed2b76b5d1dff43c4728720c	dad777d8ed2b76b5d1dff43c4728720c	girafe	faune	9	2025-11-18 17:20:55	2025-11-18 17:20:55
270	3	faune_ai_9f3e65401f47816661e3772bf4bf02bf	9f3e65401f47816661e3772bf4bf02bf	dérive océanique	faune	9	2025-11-18 17:21:24	2025-11-18 17:21:24
271	3	faune_ai_ab4e54b9d9311114a9b684a02b340489	ab4e54b9d9311114a9b684a02b340489	Éléphant d'afrique	faune	9	2025-11-18 17:57:35	2025-11-18 17:57:35
272	3	faune_ai_15907969554f8f308cabb3585589f893	15907969554f8f308cabb3585589f893	les grenouilles arborea	faune	9	2025-11-18 17:58:02	2025-11-18 17:58:02
273	3	faune_ai_ebc3d28cbf2cccb19f65256a2cd01dec	ebc3d28cbf2cccb19f65256a2cd01dec	la seiche	faune	9	2025-11-18 17:59:05	2025-11-18 17:59:05
274	3	faune_ai_1b07b8548209229afd4150e509cc1086	1b07b8548209229afd4150e509cc1086	corbeau	faune	9	2025-11-18 17:59:36	2025-11-18 17:59:36
275	3	faune_ai_c2025cc416bb76059438118f9d8b00bd	c2025cc416bb76059438118f9d8b00bd	poisson clown	faune	9	2025-11-18 18:00:04	2025-11-18 18:00:04
276	3	faune_ai_f519c3fd6d8626eb234262c59144fada	f519c3fd6d8626eb234262c59144fada	raton laveur	faune	9	2025-11-18 18:00:37	2025-11-18 18:00:37
277	3	faune_ai_8dc1194210c6f5fd0f601e9e609bc204	8dc1194210c6f5fd0f601e9e609bc204	le rats de champ	faune	9	2025-11-18 18:01:08	2025-11-18 18:01:08
278	3	faune_ai_7d873c85ac72b326b19db03b2aa8a8f0	7d873c85ac72b326b19db03b2aa8a8f0	cacatoès	faune	9	2025-11-18 18:01:39	2025-11-18 18:01:39
279	3	faune_ai_facd68670e7d8f8f776048031644b110	facd68670e7d8f8f776048031644b110	le merle	faune	9	2025-11-18 18:02:04	2025-11-18 18:02:04
280	3	faune_ai_d77da01e43576e6f3fcc28f3c94dcc40	d77da01e43576e6f3fcc28f3c94dcc40	l'oiseau de paradis	faune	9	2025-11-18 18:02:33	2025-11-18 18:02:33
281	3	faune_ai_7b40b10d973f34405fef41899684d099	7b40b10d973f34405fef41899684d099	souris	faune	9	2025-11-18 19:16:00	2025-11-18 19:16:00
282	3	faune_ai_214950700879be4ca3e927103310a1ab	214950700879be4ca3e927103310a1ab	chauve-souris	faune	9	2025-11-18 19:16:50	2025-11-18 19:16:50
283	3	faune_ai_3c7c98fa8ab84a68f366e7d7e488ba2d	3c7c98fa8ab84a68f366e7d7e488ba2d	grenouille	faune	9	2025-11-18 19:17:28	2025-11-18 19:17:28
284	3	faune_ai_4b2244d54d35e36e9904f79182535d55	4b2244d54d35e36e9904f79182535d55	les zones tropicales peu profondes	faune	9	2025-11-18 19:18:11	2025-11-18 19:18:11
285	3	faune_ai_602dcf7109d1ea80cc1e643270237319	602dcf7109d1ea80cc1e643270237319	l'endurolâtre	faune	9	2025-11-18 19:18:51	2025-11-18 19:18:51
286	3	faune_ai_66cc3179086e0967de16eca3e021efe3	66cc3179086e0967de16eca3e021efe3	le paresseux	faune	9	2025-11-18 19:19:27	2025-11-18 19:19:27
287	3	faune_ai_6c22876d97782078d0222f880fa698ae	6c22876d97782078d0222f880fa698ae	le oiseau de paradis	faune	9	2025-11-18 19:32:59	2025-11-18 19:32:59
288	3	faune_ai_b93a7f7479ad425a26168ac423c6c2c3	b93a7f7479ad425a26168ac423c6c2c3	l'assainissement des sols	faune	9	2025-11-18 19:33:25	2025-11-18 19:33:25
289	3	faune_ai_351718484523348c24bad376335d4081	351718484523348c24bad376335d4081	Éléphant	faune	9	2025-11-18 19:34:12	2025-11-18 19:34:12
290	3	faune_ai_1de726415331443414495003343377dc	1de726415331443414495003343377dc	coq	faune	9	2025-11-18 19:34:14	2025-11-18 19:34:14
291	3	faune_ai_ea7ffa9ae34eeb86fcda84100a89ea37	ea7ffa9ae34eeb86fcda84100a89ea37	le hibou	faune	9	2025-11-18 19:34:45	2025-11-18 19:34:45
292	3	faune_ai_b14f46cd4e2f1f3879100694bec3002a	b14f46cd4e2f1f3879100694bec3002a	sardine	faune	9	2025-11-18 19:35:08	2025-11-18 19:35:08
293	3	faune_ai_a2c6745a3d20b4bc0d5f4c228936afc4	a2c6745a3d20b4bc0d5f4c228936afc4	les abeilles	faune	9	2025-11-18 19:35:34	2025-11-18 19:35:34
294	3	faune_ai_3b45c3d1053598f44916f96e4ec8c3f2	3b45c3d1053598f44916f96e4ec8c3f2	l'écureuil	faune	9	2025-11-18 19:36:03	2025-11-18 19:36:03
295	3	faune_ai_687326621c97c8b41d4f502a715c26a4	687326621c97c8b41d4f502a715c26a4	la libellule	faune	9	2025-11-18 19:36:32	2025-11-18 19:36:32
296	3	faune_ai_b4aa00f4d274ac38777e6312186f0f0f	b4aa00f4d274ac38777e6312186f0f0f	les rivières et les lacs	faune	9	2025-11-18 19:37:08	2025-11-18 19:37:08
297	3	faune_ai_12c2a799f5731a233b46eea213c75078	12c2a799f5731a233b46eea213c75078	le tisserin	faune	9	2025-11-18 19:52:17	2025-11-18 19:52:17
298	3	faune_ai_e3ad4fb368962b9c30ee89958079ba68	e3ad4fb368962b9c30ee89958079ba68	ecureuil	faune	9	2025-11-18 19:52:54	2025-11-18 19:52:54
299	3	faune_ai_889039cf6590f068ae28dc6ba5349fdb	889039cf6590f068ae28dc6ba5349fdb	la raie	faune	9	2025-11-18 19:53:31	2025-11-18 19:53:31
300	3	faune_ai_ea68e82b9a62050fa916ddc82e39f355	ea68e82b9a62050fa916ddc82e39f355	écureuil	faune	9	2025-11-18 19:53:58	2025-11-18 19:53:58
301	3	faune_ai_85fd56115d6512a116b45826ed271a22	85fd56115d6512a116b45826ed271a22	le chant des anoures	faune	9	2025-11-18 19:54:31	2025-11-18 19:54:31
302	3	faune_ai_4df33d0d76c940d77e031bff2ff06ce5	4df33d0d76c940d77e031bff2ff06ce5	ornithorynque	faune	9	2025-11-18 19:55:11	2025-11-18 19:55:11
303	3	faune_ai_4b6bb0235ffea44e20aac36df0a07a0e	4b6bb0235ffea44e20aac36df0a07a0e	la tortue	faune	9	2025-11-18 19:55:53	2025-11-18 19:55:53
304	3	faune_ai_18c8f1b2eb0c3cea70f3cc486950b8c3	18c8f1b2eb0c3cea70f3cc486950b8c3	le scarabée	faune	9	2025-11-18 19:56:28	2025-11-18 19:56:28
305	3	faune_ai_f964f418d62e3f1fdb0d6077baa4b408	f964f418d62e3f1fdb0d6077baa4b408	perroquet	faune	9	2025-11-18 19:57:09	2025-11-18 19:57:09
306	3	faune_ai_b38e0357d6d6f8d4617f1cc5399d80b5	b38e0357d6d6f8d4617f1cc5399d80b5	tigre	faune	9	2025-11-18 19:57:11	2025-11-18 19:57:11
307	3	faune_ai_30c80901ee9eb21083ed61560b1c89e9	30c80901ee9eb21083ed61560b1c89e9	colibri	faune	9	2025-11-18 19:57:44	2025-11-18 19:57:44
308	3	faune_ai_c967366c2668bb9700d4ed2aa1be2a2c	c967366c2668bb9700d4ed2aa1be2a2c	le rossignol	faune	9	2025-11-18 19:58:14	2025-11-18 19:58:14
309	3	faune_ai_d7ff4bebbb68f7dfd48dd9b37a8db227	d7ff4bebbb68f7dfd48dd9b37a8db227	l'hippocampe	faune	9	2025-11-18 22:12:55	2025-11-18 22:12:55
310	3	faune_ai_23f6b8856cb5bb5873441e3c94a424c7	23f6b8856cb5bb5873441e3c94a424c7	arara	faune	9	2025-11-18 22:13:24	2025-11-18 22:13:24
311	3	faune_ai_ba2c516f5018bfbac0e8c461392a855f	ba2c516f5018bfbac0e8c461392a855f	le yak	faune	9	2025-11-18 22:14:04	2025-11-18 22:14:04
312	3	faune_ai_32baf116c6fe38e268a77415d4971252	32baf116c6fe38e268a77415d4971252	lapin	faune	9	2025-11-18 22:14:40	2025-11-18 22:14:40
313	3	faune_ai_5120d142a98ee4af2ee819de7a8d04fd	5120d142a98ee4af2ee819de7a8d04fd	la roussette	faune	9	2025-11-18 22:15:15	2025-11-18 22:15:15
314	3	faune_ai_94e0d347cf56999fc2c5869d9b967daf	94e0d347cf56999fc2c5869d9b967daf	l'abeille	faune	9	2025-11-18 22:15:51	2025-11-18 22:15:51
315	3	faune_ai_b48da43fd0c5919136998f67646d42a3	b48da43fd0c5919136998f67646d42a3	la chouette	faune	9	2025-11-18 22:16:29	2025-11-18 22:16:29
316	3	faune_ai_dae54276c4b984ce5b399e606fd3c90d	dae54276c4b984ce5b399e606fd3c90d	le kangourou	faune	9	2025-11-18 22:17:03	2025-11-18 22:17:03
317	3	faune_ai_328e2f26229a8c61233d257d10b00809	328e2f26229a8c61233d257d10b00809	phoque	faune	9	2025-11-18 22:17:29	2025-11-18 22:17:29
318	3	faune_ai_1875e858963c6cb3ab6159a6e3191d2f	1875e858963c6cb3ab6159a6e3191d2f	le pingouin	faune	9	2025-11-18 22:18:07	2025-11-18 22:18:07
319	3	faune_ai_cb265cf11fad3741f1ccbd09b10dcbe6	cb265cf11fad3741f1ccbd09b10dcbe6	Éperlan	faune	9	2025-11-18 22:18:09	2025-11-18 22:18:09
320	3	histoire_ai_b51d9fc95c04f29f951706e3fe4a1a4f	b51d9fc95c04f29f951706e3fe4a1a4f	l'érable à sucre	histoire	10	2025-11-19 14:15:27	2025-11-19 14:15:27
321	3	histoire_ai_f0b26f7e8e83553f570efe524a2c3506	f0b26f7e8e83553f570efe524a2c3506	cheetah	histoire	10	2025-11-19 14:16:03	2025-11-19 14:16:03
322	3	histoire_ai_d9b109aa8b9ef60484a9c81fef62dec0	d9b109aa8b9ef60484a9c81fef62dec0	poisson-chat	histoire	10	2025-11-19 14:16:41	2025-11-19 14:16:41
323	3	histoire_ai_7512e87602d087cd039979174d93b669	7512e87602d087cd039979174d93b669	le cheval	histoire	10	2025-11-19 14:17:18	2025-11-19 14:17:18
324	3	histoire_ai_cb4491b3f3cd7ad4923b788ec9f47713	cb4491b3f3cd7ad4923b788ec9f47713	les tortues	histoire	10	2025-11-19 14:17:40	2025-11-19 14:17:40
325	3	histoire_ai_4eb96254db03483c66cba5f14d67e5c3	4eb96254db03483c66cba5f14d67e5c3	incas	histoire	10	2025-11-19 14:18:08	2025-11-19 14:18:08
326	3	histoire_ai_74de519e66ac709d6c3bd3eb4553b1e0	74de519e66ac709d6c3bd3eb4553b1e0	le chat	histoire	10	2025-11-19 14:18:30	2025-11-19 14:18:30
327	3	histoire_ai_6c05e6ed02dad023817e457e41a0222f	6c05e6ed02dad023817e457e41a0222f	bons baisers de lyon	histoire	10	2025-11-19 14:18:59	2025-11-19 14:18:59
328	3	histoire_ai_e242b2a05f76c05fb0aa674299944fe9	e242b2a05f76c05fb0aa674299944fe9	louis xiv	histoire	10	2025-11-19 14:19:38	2025-11-19 14:19:38
329	3	histoire_ai_29656b5aa8a8fce8866fdb95f5c08b0a	29656b5aa8a8fce8866fdb95f5c08b0a	poisson-perroquet	histoire	10	2025-11-19 14:20:09	2025-11-19 14:20:09
330	3	histoire_ai_00c8129d2133d3adf1fdbee127155b95	00c8129d2133d3adf1fdbee127155b95	le cerisier	histoire	10	2025-11-19 14:20:36	2025-11-19 14:20:36
331	3	histoire_ai_25183a162bcc147c919c626d7ae353bf	25183a162bcc147c919c626d7ae353bf	révolution française	histoire	10	2025-11-19 14:21:10	2025-11-19 14:21:10
332	3	histoire_ai_a52565fba673a4480c908e52b714b3c2	a52565fba673a4480c908e52b714b3c2	la girafe	histoire	10	2025-11-19 14:21:15	2025-11-19 14:21:15
333	3	histoire_ai_cbbeda3bad7a3d2f2925b0971a7e46c2	cbbeda3bad7a3d2f2925b0971a7e46c2	pomme de terre	histoire	10	2025-11-19 14:21:48	2025-11-19 14:21:48
334	3	histoire_ai_fbe41c56839e02b94c10aee26580a4da	fbe41c56839e02b94c10aee26580a4da	mammouth	histoire	10	2025-11-19 14:22:32	2025-11-19 14:22:32
335	3	histoire_ai_77e3796dfd2e5648e28adfa79cc8ee07	77e3796dfd2e5648e28adfa79cc8ee07	zacharias janssen	histoire	10	2025-11-19 14:23:01	2025-11-19 14:23:01
336	3	histoire_ai_47db8c4eb826114c886173fbcd3b3f1d	47db8c4eb826114c886173fbcd3b3f1d	guillaume le conquérant	histoire	10	2025-11-19 14:23:20	2025-11-19 14:23:20
337	3	histoire_ai_af3aaf5d1e4072f789ca2f1bbbaee7b1	af3aaf5d1e4072f789ca2f1bbbaee7b1	khéops	histoire	10	2025-11-19 14:23:51	2025-11-19 14:23:51
338	3	histoire_ai_d93544916e173b7286b4bdb01c374b16	d93544916e173b7286b4bdb01c374b16	gizeh	histoire	10	2025-11-19 14:24:12	2025-11-19 14:24:12
339	3	histoire_ai_9768f2b435d9063380723127de20c0a6	9768f2b435d9063380723127de20c0a6	les pyramides de gizeh	histoire	10	2025-11-19 14:24:38	2025-11-19 14:24:38
340	3	histoire_ai_3dfc720b882d1648b4bc6f72ac670ede	3dfc720b882d1648b4bc6f72ac670ede	érable	histoire	10	2025-11-19 14:25:01	2025-11-19 14:25:01
341	3	histoire_ai_199994c223fb26bd96b7c836485d83c0	199994c223fb26bd96b7c836485d83c0	noyer	histoire	10	2025-11-19 14:25:27	2025-11-19 14:25:27
342	3	histoire_ai_cece8b07ebf08ae5d749fadae2110340	cece8b07ebf08ae5d749fadae2110340	christophe colomb	histoire	10	2025-11-19 14:25:52	2025-11-19 14:25:52
343	3	faune_ai_0dd716b83c49b1ebc4de7bc504732bf7	0dd716b83c49b1ebc4de7bc504732bf7	le guépard	faune	10	2025-11-19 22:47:59	2025-11-19 22:47:59
344	3	faune_ai_c05677882c8d18a30a45311621e279fb	c05677882c8d18a30a45311621e279fb	le thon	faune	10	2025-11-19 22:56:39	2025-11-19 22:56:39
345	3	faune_ai_fd1582e7319e8411b88d2f9cfb6d4924	fd1582e7319e8411b88d2f9cfb6d4924	le lapin	faune	10	2025-11-19 22:57:11	2025-11-19 22:57:11
346	3	faune_ai_aae6f89e64fe46f488c1c8eac4225855	aae6f89e64fe46f488c1c8eac4225855	l'éléphant d'afrique	faune	10	2025-11-19 22:57:38	2025-11-19 22:57:38
347	3	faune_ai_874d3335322dc8dced12d8ef73bb0e9e	874d3335322dc8dced12d8ef73bb0e9e	faucon pèlerin	faune	10	2025-11-19 22:58:07	2025-11-19 22:58:07
348	3	faune_ai_a8d31d39de96886f2c010f91fc3846e6	a8d31d39de96886f2c010f91fc3846e6	l'opossum	faune	10	2025-11-19 22:58:37	2025-11-19 22:58:37
349	3	faune_ai_529e197db7c9c8d4ba89124e6f91555d	529e197db7c9c8d4ba89124e6f91555d	guépard	faune	10	2025-11-19 22:59:07	2025-11-19 22:59:07
350	3	faune_ai_e80d74eca56a04c5a089074e1b9b2715	e80d74eca56a04c5a089074e1b9b2715	lion	faune	10	2025-11-19 22:59:32	2025-11-19 22:59:32
351	3	faune_ai_4d518a6bdc3d37cb2c653b2f9db4f6a4	4d518a6bdc3d37cb2c653b2f9db4f6a4	le cachalot	faune	10	2025-11-19 22:59:56	2025-11-19 22:59:56
352	3	faune_ai_2ecc7b7747a88f9ac16709a477c88082	2ecc7b7747a88f9ac16709a477c88082	le phalanger volant	faune	10	2025-11-19 23:00:18	2025-11-19 23:00:18
353	3	faune_ai_cb7d9b888e6dd505d7f16baddd3b777e	cb7d9b888e6dd505d7f16baddd3b777e	le krakken	faune	10	2025-11-19 23:00:49	2025-11-19 23:00:49
354	3	faune_ai_0d3d487d2f78508b0ef34fa682fd1e17	0d3d487d2f78508b0ef34fa682fd1e17	la baleine bleue	faune	10	2025-11-19 23:02:27	2025-11-19 23:02:27
355	3	faune_ai_189dca8cfb7292fe8e4f42150d042d33	189dca8cfb7292fe8e4f42150d042d33	le lion	faune	10	2025-11-19 23:02:58	2025-11-19 23:02:58
356	3	faune_ai_4ad91243691b3695429b8f210e6a62e5	4ad91243691b3695429b8f210e6a62e5	rhinocéros	faune	10	2025-11-19 23:03:22	2025-11-19 23:03:22
357	3	faune_ai_2adc46e17bb92cbf339bab4b612e8444	2adc46e17bb92cbf339bab4b612e8444	le leopard	faune	10	2025-11-19 23:03:56	2025-11-19 23:03:56
358	3	faune_ai_e582a2df43c9989c03fd3656da665519	e582a2df43c9989c03fd3656da665519	baleine bleue	faune	10	2025-11-19 23:04:28	2025-11-19 23:04:28
359	3	faune_ai_18c7255bb3f2a8eab1cb1a664af9b4bf	18c7255bb3f2a8eab1cb1a664af9b4bf	méduse	faune	10	2025-11-19 23:04:29	2025-11-19 23:04:29
360	3	faune_ai_7157b6326827fcc1a170444f898c2dfc	7157b6326827fcc1a170444f898c2dfc	le requin baleine	faune	10	2025-11-19 23:04:53	2025-11-19 23:04:53
361	3	faune_ai_ddf894d30e72bb6840c27b6d1fd8157d	ddf894d30e72bb6840c27b6d1fd8157d	le pélican	faune	10	2025-11-19 23:05:18	2025-11-19 23:05:18
362	3	faune_ai_fc267bdeea4292dcb007ef8f02d2277c	fc267bdeea4292dcb007ef8f02d2277c	le rhinocéros	faune	10	2025-11-19 23:05:42	2025-11-19 23:05:42
363	3	faune_ai_228b938f949db728058d71c05a1b9a20	228b938f949db728058d71c05a1b9a20	l'hippopotame	faune	10	2025-11-19 23:06:03	2025-11-19 23:06:03
364	3	faune_ai_98bdbe25f92f537fe6802825cb18e6e5	98bdbe25f92f537fe6802825cb18e6e5	paon	faune	10	2025-11-19 23:06:26	2025-11-19 23:06:26
365	3	faune_ai_8ab0b85503e0d83355dd323eddd98c53	8ab0b85503e0d83355dd323eddd98c53	120 km/h	faune	10	2025-11-19 23:50:45	2025-11-19 23:50:45
366	3	faune_ai_366f85aab5c2c9116c3df49ef070531c	366f85aab5c2c9116c3df49ef070531c	l'axolotl	faune	10	2025-11-19 23:51:20	2025-11-19 23:51:20
367	3	faune_ai_dfb53697a86bf0632df8fc1493fd5221	dfb53697a86bf0632df8fc1493fd5221	le guépard	faune	10	2025-11-19 23:58:27	2025-11-19 23:58:27
368	3	faune_ai_c9ee6da827eb701c586ad339e2e812f6	c9ee6da827eb701c586ad339e2e812f6	le marlin	faune	10	2025-11-19 23:58:46	2025-11-19 23:58:46
369	3	faune_ai_e10bf3bcbcff7321672152ed522770e7	e10bf3bcbcff7321672152ed522770e7	le fennec	faune	10	2025-11-19 23:59:16	2025-11-19 23:59:16
370	3	faune_ai_ef266067677430119181ad38f4359e7f	ef266067677430119181ad38f4359e7f	le serpent	faune	10	2025-11-19 23:59:47	2025-11-19 23:59:47
371	3	faune_ai_f18bba4f6a0d18b6519e91441149e2a5	f18bba4f6a0d18b6519e91441149e2a5	la tortue galapaguensis	faune	10	2025-11-20 00:00:12	2025-11-20 00:00:12
372	3	faune_ai_d36396dd73e9b95e835c055dc4e707dc	d36396dd73e9b95e835c055dc4e707dc	l'éléphant	faune	10	2025-11-20 00:00:42	2025-11-20 00:00:42
373	3	faune_ai_7123f4297bea9dfcc3f7b966c68712bd	7123f4297bea9dfcc3f7b966c68712bd	la pieuvre	faune	10	2025-11-20 00:01:14	2025-11-20 00:01:14
374	3	faune_ai_873c657301c5e17cbd1ce1b9de0e028d	873c657301c5e17cbd1ce1b9de0e028d	le bar tendre	faune	10	2025-11-20 00:01:41	2025-11-20 00:01:41
375	3	faune_ai_26da99d81535601814a936346bf55fb7	26da99d81535601814a936346bf55fb7	la poitrine d'or	faune	10	2025-11-20 00:02:19	2025-11-20 00:02:19
376	3	faune_ai_5f59086bf685c4ec1f4ea0f880ba0669	5f59086bf685c4ec1f4ea0f880ba0669	la baleine boréale	faune	10	2025-11-20 00:02:58	2025-11-20 00:02:58
377	3	faune_ai_15c4196db7dc7100a31615f0450b44f0	15c4196db7dc7100a31615f0450b44f0	la grenouille	faune	10	2025-11-20 00:12:55	2025-11-20 00:12:55
378	3	faune_ai_7c650724ab17ccd9f0a6c63a6bfae365	7c650724ab17ccd9f0a6c63a6bfae365	le thon	faune	10	2025-11-20 00:13:34	2025-11-20 00:13:34
379	3	faune_ai_238f178ad93356c2a0468cc07f29cb2f	238f178ad93356c2a0468cc07f29cb2f	faux	faune	10	2025-11-20 00:13:37	2025-11-20 00:13:37
380	3	faune_ai_c4eb3b02de1b729da490560f9c1f0bdd	c4eb3b02de1b729da490560f9c1f0bdd	le kangourou	faune	10	2025-11-20 00:13:58	2025-11-20 00:13:58
381	3	faune_ai_318142582e608cb1edb4324622eca19e	318142582e608cb1edb4324622eca19e	le chameau	faune	10	2025-11-20 00:14:25	2025-11-20 00:14:25
382	3	faune_ai_cb3b8fa9ec1b80b23342755a9422100c	cb3b8fa9ec1b80b23342755a9422100c	le colibri	faune	10	2025-11-20 00:14:48	2025-11-20 00:14:48
383	3	faune_ai_b0b7c3f7ca036112e97dc8dc2a0a12f9	b0b7c3f7ca036112e97dc8dc2a0a12f9	le petit corps de l'abbaye	faune	10	2025-11-20 00:15:07	2025-11-20 00:15:07
384	3	faune_ai_c63505149da84534e810720dc58b5c6c	c63505149da84534e810720dc58b5c6c	le hérisson	faune	10	2025-11-20 00:15:41	2025-11-20 00:15:41
385	3	faune_ai_3a5a5e00b4e129116fdfa3c09c30f1f5	3a5a5e00b4e129116fdfa3c09c30f1f5	l'éléphant d'afrique	faune	10	2025-11-20 00:16:19	2025-11-20 00:16:19
386	3	faune_ai_6186406c9350ccbcf889120c853accd4	6186406c9350ccbcf889120c853accd4	le voilier	faune	10	2025-11-20 00:16:42	2025-11-20 00:16:42
387	3	faune_ai_2429334fe539d0d1d12bf43bf64d2fed	2429334fe539d0d1d12bf43bf64d2fed	le kangourou rat	faune	10	2025-11-20 00:17:16	2025-11-20 00:17:16
388	3	faune_ai_23db37a49f928b14a2cf95df4c97d18e	23db37a49f928b14a2cf95df4c97d18e	guépard	faune	10	2025-11-20 00:43:27	2025-11-20 00:43:27
389	3	faune_ai_d21935f7586e11f4451c7ae3c3e3d111	d21935f7586e11f4451c7ae3c3e3d111	autruche	faune	10	2025-11-20 00:43:45	2025-11-20 00:43:45
390	3	faune_ai_83b0e4b9c67bb410f7b5c874d688b33f	83b0e4b9c67bb410f7b5c874d688b33f	abeille	faune	10	2025-11-20 00:44:10	2025-11-20 00:44:10
391	3	faune_ai_83f93a8bc30eb7bd3ba853a1d9fed0ae	83f93a8bc30eb7bd3ba853a1d9fed0ae	ours polaire	faune	10	2025-11-20 00:44:42	2025-11-20 00:44:42
392	3	faune_ai_a27467eab1ca543b4cae872f82f9afc1	a27467eab1ca543b4cae872f82f9afc1	lapin	faune	10	2025-11-20 00:45:08	2025-11-20 00:45:08
393	3	faune_ai_6af5f8f28663288cfa80692af4394b6d	6af5f8f28663288cfa80692af4394b6d	fennec	faune	10	2025-11-20 00:45:32	2025-11-20 00:45:32
394	3	faune_ai_e99ab8b73037fb1ebbac61094570786a	e99ab8b73037fb1ebbac61094570786a	fourmi	faune	10	2025-11-20 00:46:07	2025-11-20 00:46:07
395	3	faune_ai_cbca0056f62dea79095a27243f3833ab	cbca0056f62dea79095a27243f3833ab	éléphant	faune	10	2025-11-20 00:46:30	2025-11-20 00:46:30
396	3	faune_ai_e7c9b59052eebf378826bde1a9b672c9	e7c9b59052eebf378826bde1a9b672c9	zèbre	faune	10	2025-11-20 00:46:50	2025-11-20 00:46:50
397	3	faune_ai_9f925b3f5111e5ae4bd06fbe4aba219f	9f925b3f5111e5ae4bd06fbe4aba219f	grand requin blanc	faune	10	2025-11-20 00:47:10	2025-11-20 00:47:10
398	3	faune_ai_e30ae5c47597007a0fb59eae208a7217	e30ae5c47597007a0fb59eae208a7217	kangourou	faune	10	2025-11-20 00:47:39	2025-11-20 00:47:39
399	3	faune_ai_02420263d8e3368056e0c4c0c1b48518	02420263d8e3368056e0c4c0c1b48518	guepard	faune	10	2025-11-20 00:54:53	2025-11-20 00:54:53
400	3	faune_ai_04e551085b7ebe4c11605672e6692ec7	04e551085b7ebe4c11605672e6692ec7	axolotl	faune	10	2025-11-20 00:55:29	2025-11-20 00:55:29
401	3	faune_ai_7b7ee87e97f276f803954678a66d4891	7b7ee87e97f276f803954678a66d4891	cachalot	faune	10	2025-11-20 00:56:02	2025-11-20 00:56:02
402	3	faune_ai_0328182fc864b7eb723246e3865892ce	0328182fc864b7eb723246e3865892ce	le flamingo	faune	10	2025-11-20 00:56:25	2025-11-20 00:56:25
403	3	faune_ai_902e8b597e605ad37cfcb334805de9d7	902e8b597e605ad37cfcb334805de9d7	canard	faune	10	2025-11-20 00:56:54	2025-11-20 00:56:54
404	3	faune_ai_dba07a463099246f158c2fa387c04579	dba07a463099246f158c2fa387c04579	faux	faune	10	2025-11-20 00:57:16	2025-11-20 00:57:16
405	3	faune_ai_af5b9f8d7f55bb0585631a754bdd0f6c	af5b9f8d7f55bb0585631a754bdd0f6c	poisson-licorne	faune	10	2025-11-20 00:57:35	2025-11-20 00:57:35
406	3	faune_ai_2e5bae95ca8c967767640b488787b678	2e5bae95ca8c967767640b488787b678	autruche	faune	10	2025-11-20 00:58:10	2025-11-20 00:58:10
407	3	faune_ai_2261bd0f8e115f264f8e33d3792de58d	2261bd0f8e115f264f8e33d3792de58d	dauphin	faune	10	2025-11-20 00:58:39	2025-11-20 00:58:39
408	3	faune_ai_68ee694135fd28eff552a5adae2ab445	68ee694135fd28eff552a5adae2ab445	chèvre	faune	10	2025-11-20 00:59:04	2025-11-20 00:59:04
409	3	faune_ai_a8d52f7219b46e656ddb5a5c5cb886e7	a8d52f7219b46e656ddb5a5c5cb886e7	vrai	faune	10	2025-11-20 01:01:45	2025-11-20 01:01:45
410	3	faune_ai_c9d67b748a9636d355bbcb5cc7c2d3d5	c9d67b748a9636d355bbcb5cc7c2d3d5	loup	faune	10	2025-11-20 01:02:09	2025-11-20 01:02:09
411	3	faune_ai_1564a4a7cf27dad4872e390e767a76ad	1564a4a7cf27dad4872e390e767a76ad	éléphant	faune	10	2025-11-20 01:02:30	2025-11-20 01:02:30
412	3	faune_ai_687479fa50afc6601b179b0b7890ddfe	687479fa50afc6601b179b0b7890ddfe	girafe	faune	10	2025-11-20 01:02:51	2025-11-20 01:02:51
413	3	faune_ai_e914ca7544a826ef35b0d3df517b7b3b	e914ca7544a826ef35b0d3df517b7b3b	caméléon	faune	10	2025-11-20 01:03:20	2025-11-20 01:03:20
414	3	faune_ai_76e06484bae976ef1a7f8da406b046b8	76e06484bae976ef1a7f8da406b046b8	ours polaire	faune	10	2025-11-20 01:03:46	2025-11-20 01:03:46
415	3	faune_ai_ba74eaed2104432ea4ca943bb0c310fe	ba74eaed2104432ea4ca943bb0c310fe	la colombe nain	faune	10	2025-11-20 01:04:05	2025-11-20 01:04:05
416	3	faune_ai_ff6495cc0105bf0a3b228dd92f37d3f2	ff6495cc0105bf0a3b228dd92f37d3f2	la raie	faune	10	2025-11-20 01:04:29	2025-11-20 01:04:29
417	3	faune_ai_0d5f2071f7469e158c76c9b3af78b1de	0d5f2071f7469e158c76c9b3af78b1de	orque	faune	10	2025-11-20 01:04:54	2025-11-20 01:04:54
418	3	faune_ai_9cbb68e84369445a09ad86c13614cfb9	9cbb68e84369445a09ad86c13614cfb9	antilope	faune	10	2025-11-20 01:05:14	2025-11-20 01:05:14
419	3	faune_ai_9d69eda2c921acb45cbdaf923e386814	9d69eda2c921acb45cbdaf923e386814	requin	faune	10	2025-11-20 01:05:37	2025-11-20 01:05:37
420	3	art_ai_f74280a5fba977faaba0b08ca4458ac8	f74280a5fba977faaba0b08ca4458ac8	léonard de vinci	art	10	2025-11-20 03:11:12	2025-11-20 03:11:12
421	3	art_ai_a4de58b91e83b827baf87a029acdf186	a4de58b91e83b827baf87a029acdf186	raphaël	art	10	2025-11-20 03:11:31	2025-11-20 03:11:31
422	3	art_ai_a44de8b21edb1887ebe5d05dca06f592	a44de8b21edb1887ebe5d05dca06f592	vincent van gogh	art	10	2025-11-20 03:11:57	2025-11-20 03:11:57
423	3	art_ai_0a1314e752cd0d638e7ec17ac55ba8ac	0a1314e752cd0d638e7ec17ac55ba8ac	august rodin	art	10	2025-11-20 03:12:24	2025-11-20 03:12:24
424	3	art_ai_fcc28319f3e4bcf4576a98ec75c3eff5	fcc28319f3e4bcf4576a98ec75c3eff5	mona lisa	art	10	2025-11-20 03:12:48	2025-11-20 03:12:48
425	3	art_ai_15c5b43b6e0ddc461937cbf29d525980	15c5b43b6e0ddc461937cbf29d525980	edvard munch	art	10	2025-11-20 03:13:07	2025-11-20 03:13:07
426	3	art_ai_6459f907bafcdcee5e6e04d0b9ebcde1	6459f907bafcdcee5e6e04d0b9ebcde1	guernica	art	10	2025-11-20 03:13:34	2025-11-20 03:13:34
427	3	art_ai_4f035a3e530b9e1d01e2d976b76d7547	4f035a3e530b9e1d01e2d976b76d7547	le cheval	art	10	2025-11-20 03:14:14	2025-11-20 03:14:14
428	3	art_ai_6a8d31aa53d559f0d0ae28cfad82e4d4	6a8d31aa53d559f0d0ae28cfad82e4d4	paul cézanne	art	10	2025-11-20 03:14:43	2025-11-20 03:14:43
429	3	art_ai_37607a356d6ff98cbbd5c4e34fcd6872	37607a356d6ff98cbbd5c4e34fcd6872	faux	art	10	2025-11-20 03:15:06	2025-11-20 03:15:06
430	3	art_ai_dfd85bae05e98ad40a02052764fa6e9b	dfd85bae05e98ad40a02052764fa6e9b	claude monet	art	10	2025-11-20 03:15:34	2025-11-20 03:15:34
431	3	art_ai_4a3d50867ad33c55ec7304d678e24761	4a3d50867ad33c55ec7304d678e24761	l'origine du monde	art	10	2025-11-20 03:21:04	2025-11-20 03:21:04
432	3	art_ai_99402b6a3e66510104d5d40e3cc13ee7	99402b6a3e66510104d5d40e3cc13ee7	olympia	art	10	2025-11-20 03:21:33	2025-11-20 03:21:33
433	3	art_ai_c41ea6f590dbbf9de49a19579f32ec4d	c41ea6f590dbbf9de49a19579f32ec4d	auguste rodin	art	10	2025-11-20 03:22:00	2025-11-20 03:22:00
434	3	art_ai_46a405932bc7ac4cafa4b64fd22671d8	46a405932bc7ac4cafa4b64fd22671d8	leonard de vinci	art	10	2025-11-20 03:22:26	2025-11-20 03:22:26
435	3	art_ai_b1472f494f730f24490851beb0aaee26	b1472f494f730f24490851beb0aaee26	pablo picasso	art	10	2025-11-20 03:22:48	2025-11-20 03:22:48
436	3	art_ai_0181ffb9080ffcc2b0259f8a4e5fc063	0181ffb9080ffcc2b0259f8a4e5fc063	hokusai	art	10	2025-11-20 03:23:22	2025-11-20 03:23:22
437	3	art_ai_fa0ccf45e444462894060370e8d30a36	fa0ccf45e444462894060370e8d30a36	rené magritte	art	10	2025-11-20 03:23:48	2025-11-20 03:23:48
438	3	art_ai_1b71502213b85233df52cf8694d1ae10	1b71502213b85233df52cf8694d1ae10	giorgione	art	10	2025-11-20 03:24:13	2025-11-20 03:24:13
439	3	art_ai_a66eb8c7413ef9e1cc746aa3c3e6b08b	a66eb8c7413ef9e1cc746aa3c3e6b08b	gutzon borglum	art	10	2025-11-20 03:24:43	2025-11-20 03:24:43
440	3	art_ai_53e57099df991eecea2f7b297c318591	53e57099df991eecea2f7b297c318591	léo delibes	art	10	2025-11-20 03:25:08	2025-11-20 03:25:08
441	3	cinema_ai_f9383bad26294755abeadf7a357f5dd3	f9383bad26294755abeadf7a357f5dd3	forrest gump	cinema	20	2025-11-20 16:55:47	2025-11-20 16:55:47
442	3	cinema_ai_83f4248641b89b8d5093c65ec4f1658a	83f4248641b89b8d5093c65ec4f1658a	blancanieve et les sept nains	cinema	20	2025-11-20 16:56:24	2025-11-20 16:56:24
443	3	cinema_ai_05a4b710180872ad40a01e5e1a6c6e53	05a4b710180872ad40a01e5e1a6c6e53	faux	cinema	20	2025-11-20 16:57:05	2025-11-20 16:57:05
444	3	cinema_ai_6298de8f9250ddcb8e33aec1fe16c702	6298de8f9250ddcb8e33aec1fe16c702	titanic	cinema	20	2025-11-20 16:57:39	2025-11-20 16:57:39
445	3	cinema_ai_573e233c6ab96a7157fe2253068cb555	573e233c6ab96a7157fe2253068cb555	wings	cinema	20	2025-11-20 16:58:17	2025-11-20 16:58:17
446	3	cinema_ai_c4abea5f5767898021f241ca5026ab3d	c4abea5f5767898021f241ca5026ab3d	les efforts de la marines	cinema	20	2025-11-20 16:58:54	2025-11-20 16:58:54
447	3	cinema_ai_e8661099e2b3039440aafbb094db7b8c	e8661099e2b3039440aafbb094db7b8c	vrai	cinema	20	2025-11-20 16:59:33	2025-11-20 16:59:33
448	3	cinema_ai_67c9d6efd44c678d95e48f47c4695158	67c9d6efd44c678d95e48f47c4695158	forest gump	cinema	20	2025-11-20 17:00:01	2025-11-20 17:00:01
449	3	cinema_ai_ef6fd2bf42dab5f78742bd6ce9e589ac	ef6fd2bf42dab5f78742bd6ce9e589ac	un étrange hommes-poissons	cinema	20	2025-11-20 17:00:39	2025-11-20 17:00:39
450	3	cinema_ai_4903d5ece48ebfb9ef97340cee1a91ba	4903d5ece48ebfb9ef97340cee1a91ba	dr. no	cinema	20	2025-11-20 17:01:18	2025-11-20 17:01:18
451	3	general_ai_f6bc09cc7cce528eff0571d61490d0f0	f6bc09cc7cce528eff0571d61490d0f0	japon	general	20	2025-11-20 18:45:43	2025-11-20 18:45:43
452	3	general_ai_8e126ad38127bc504b94f7020a4bfcd0	8e126ad38127bc504b94f7020a4bfcd0	vrai	general	20	2025-11-20 18:46:08	2025-11-20 18:46:08
453	3	general_ai_4d9edee12ab218af13c05684ec0104d6	4d9edee12ab218af13c05684ec0104d6	8849 mètres	general	20	2025-11-20 18:46:32	2025-11-20 18:46:32
454	3	general_ai_eeff2c8431fddc0270884a8506d48be9	eeff2c8431fddc0270884a8506d48be9	vrai	general	20	2025-11-20 18:47:03	2025-11-20 18:47:03
455	3	general_ai_afe808ed0b1b8e8b52862a3204d11f28	afe808ed0b1b8e8b52862a3204d11f28	russie	general	20	2025-11-20 18:47:25	2025-11-20 18:47:25
456	3	general_ai_6ac87d9f7e8212fe9d2e03f5a34897c8	6ac87d9f7e8212fe9d2e03f5a34897c8	pablo picasso	general	20	2025-11-20 18:47:48	2025-11-20 18:47:48
457	3	general_ai_dadfea5a760605274dffca4827f142f4	dadfea5a760605274dffca4827f142f4	faux	general	20	2025-11-20 18:48:16	2025-11-20 18:48:16
458	3	general_ai_05351b83f492c116208a37b258f9f9f1	05351b83f492c116208a37b258f9f9f1	victor hugo	general	20	2025-11-20 18:48:45	2025-11-20 18:48:45
459	3	general_ai_244d53c552730bb1e7f8645f7ae698f9	244d53c552730bb1e7f8645f7ae698f9	abbey road	general	20	2025-11-20 18:49:06	2025-11-20 18:49:06
460	3	general_ai_0b817d1115c21025e38da6fe29104d49	0b817d1115c21025e38da6fe29104d49	vrai	general	20	2025-11-20 18:49:32	2025-11-20 18:49:32
461	3	general_ai_98d520e4968adf6a8acc3d1d56819120	98d520e4968adf6a8acc3d1d56819120	océan pacifique	general	20	2025-11-20 18:51:19	2025-11-20 18:51:19
462	3	general_ai_ecf057739067b4ed8b126d6fb66d3305	ecf057739067b4ed8b126d6fb66d3305	guépard	general	20	2025-11-20 18:51:45	2025-11-20 18:51:45
463	3	general_ai_043280bdf6424da01384337638bc1428	043280bdf6424da01384337638bc1428	russie	general	20	2025-11-20 18:52:13	2025-11-20 18:52:13
464	3	general_ai_5829db3d220c2d440b8ec3d23d17b706	5829db3d220c2d440b8ec3d23d17b706	vrai	general	20	2025-11-20 18:52:47	2025-11-20 18:52:47
465	3	general_ai_6cfa40dd457ae7ea0c84840a3e79a5e1	6cfa40dd457ae7ea0c84840a3e79a5e1	eléphant d'afrique	general	20	2025-11-20 19:00:27	2025-11-20 19:00:27
466	3	general_ai_0f7a0245e63dc33b9ca33dfc13f1d16e	0f7a0245e63dc33b9ca33dfc13f1d16e	vrai	general	20	2025-11-20 19:00:59	2025-11-20 19:00:59
467	3	general_ai_89af13c1cb5ae5d18425244446913e24	89af13c1cb5ae5d18425244446913e24	Égypte	general	20	2025-11-20 19:01:23	2025-11-20 19:01:23
468	3	general_ai_41d1787246b51cf3f4e4d3fa18e6abc0	41d1787246b51cf3f4e4d3fa18e6abc0	russie	general	20	2025-11-20 19:01:51	2025-11-20 19:01:51
469	3	general_ai_ca4a47e85408e78f4ee4b0c78e97eca5	ca4a47e85408e78f4ee4b0c78e97eca5	l'atlantique	general	20	2025-11-20 19:02:13	2025-11-20 19:02:13
470	3	general_ai_40a87cb3f710f103a3437b473840c719	40a87cb3f710f103a3437b473840c719	paresseux	general	20	2025-11-20 19:02:34	2025-11-20 19:02:34
471	3	general_ai_66ada2540fa4a403d56985b500370c16	66ada2540fa4a403d56985b500370c16	vrai	general	20	2025-11-20 19:02:51	2025-11-20 19:02:51
472	3	general_ai_ab197bca93dd3a7f5c536aa362738d9e	ab197bca93dd3a7f5c536aa362738d9e	aigle chauve	general	20	2025-11-20 19:03:12	2025-11-20 19:03:12
473	3	general_ai_a4e3a32b2be0d179f71efebe088d23bb	a4e3a32b2be0d179f71efebe088d23bb	vrai	general	20	2025-11-20 19:03:30	2025-11-20 19:03:30
474	3	general_ai_b5ee4077f2a54375346294082b05898f	b5ee4077f2a54375346294082b05898f	1969	general	20	2025-11-20 19:03:46	2025-11-20 19:03:46
475	3	general_ai_87dad638353048e83dad26301719be1a	87dad638353048e83dad26301719be1a	albert einstein	general	20	2025-11-20 19:04:35	2025-11-20 19:04:35
476	3	general_ai_4b89667f636565a39e82af9346984dd2	4b89667f636565a39e82af9346984dd2	lapin	general	20	2025-11-20 19:04:52	2025-11-20 19:04:52
477	3	general_ai_252eac9ef64abda5be89692bfcc16e7a	252eac9ef64abda5be89692bfcc16e7a	mont everest	general	20	2025-11-20 19:05:12	2025-11-20 19:05:12
478	3	general_ai_33a1f0da67ca3a18cf084faec77fef36	33a1f0da67ca3a18cf084faec77fef36	canada	general	20	2025-11-20 19:05:29	2025-11-20 19:05:29
479	3	general_ai_53d5990c0dcbe145cd3a9f5a1d6c8e77	53d5990c0dcbe145cd3a9f5a1d6c8e77	8849 mètres	general	20	2025-11-20 19:05:48	2025-11-20 19:05:48
480	3	general_ai_c709c697ed9cd68a947c8f03618d80ba	c709c697ed9cd68a947c8f03618d80ba	mont everest	general	20	2025-11-20 19:06:04	2025-11-20 19:06:04
481	3	general_ai_6b791fbf0aecde13ad804d9d657df770	6b791fbf0aecde13ad804d9d657df770	chêne	general	20	2025-11-20 19:06:31	2025-11-20 19:06:31
482	3	general_ai_9d1c8fb9fb7d4ab1503e5f9ca1a0d356	9d1c8fb9fb7d4ab1503e5f9ca1a0d356	la pierre	general	20	2025-11-20 19:06:51	2025-11-20 19:06:51
483	3	general_ai_f73097a236383c221e91cb2ececf7367	f73097a236383c221e91cb2ececf7367	victor hugo	general	20	2025-11-20 19:07:14	2025-11-20 19:07:14
484	3	general_ai_6fe28b3eced514265880aac9eb80f1b3	6fe28b3eced514265880aac9eb80f1b3	chine	general	20	2025-11-20 19:07:33	2025-11-20 19:07:33
485	3	general_ai_1efeaf234e1ba1c4e0dac0d1cae76826	1efeaf234e1ba1c4e0dac0d1cae76826	le nil	general	30	2025-11-21 01:09:06	2025-11-21 01:09:06
486	3	general_ai_90f18d6edc57dcb297ab8f603e61cf0c	90f18d6edc57dcb297ab8f603e61cf0c	bahamas	general	30	2025-11-21 01:09:43	2025-11-21 01:09:43
487	3	general_ai_67ecda450f7e0a244544dd247e288437	67ecda450f7e0a244544dd247e288437	or	general	30	2025-11-21 01:10:22	2025-11-21 01:10:22
488	3	general_ai_64acb5cd19d9dfd96bc2491321b52553	64acb5cd19d9dfd96bc2491321b52553	vrai	general	30	2025-11-21 01:10:57	2025-11-21 01:10:57
489	3	general_ai_b2e403ad93575201543c36a95dd5ef8a	b2e403ad93575201543c36a95dd5ef8a	russie	general	30	2025-11-21 01:11:22	2025-11-21 01:11:22
490	3	general_ai_fba04f15e2e8e503954b5f3cfb206fe8	fba04f15e2e8e503954b5f3cfb206fe8	cachalot	general	30	2025-11-21 01:11:52	2025-11-21 01:11:52
491	3	general_ai_a16aad4d2cfb64b18709accb16dc8382	a16aad4d2cfb64b18709accb16dc8382	michel-ange	general	30	2025-11-21 01:12:21	2025-11-21 01:12:21
492	3	general_ai_5ad2adc1ba4801a50a9d12717baa0a38	5ad2adc1ba4801a50a9d12717baa0a38	nouvelle-zélande	general	30	2025-11-21 01:12:53	2025-11-21 01:12:53
493	3	general_ai_c1d687cd7e6ab38d8e758649451abdd0	c1d687cd7e6ab38d8e758649451abdd0	le télégraphe	general	30	2025-11-21 01:13:11	2025-11-21 01:13:11
494	3	general_ai_d2fdeb08172966adbbe03e1ec4d21df5	d2fdeb08172966adbbe03e1ec4d21df5	vrai	general	30	2025-11-21 01:13:44	2025-11-21 01:13:44
495	3	general_ai_31a16e62732f1b029ac297dce4c5fcce	31a16e62732f1b029ac297dce4c5fcce	vallée des mariannes	general	30	2025-11-21 01:15:05	2025-11-21 01:15:05
496	3	general_ai_8e84e18608c4b4d3ba7d4678b5e683ec	8e84e18608c4b4d3ba7d4678b5e683ec	brésil	general	30	2025-11-21 01:15:34	2025-11-21 01:15:34
497	3	general_ai_d08c7519960a3e940b0c6dc461567043	d08c7519960a3e940b0c6dc461567043	finlande	general	30	2025-11-21 01:15:53	2025-11-21 01:15:53
498	3	general_ai_d2a8a81d80217f394ae9ff9b1834ca95	d2a8a81d80217f394ae9ff9b1834ca95	vrai	general	30	2025-11-21 01:16:11	2025-11-21 01:16:11
499	3	general_ai_26237c4d9227f314ab550c792ec1360c	26237c4d9227f314ab550c792ec1360c	vrai	general	30	2025-11-21 01:16:35	2025-11-21 01:16:35
500	3	general_ai_9ec742d0746f2bfe5096d55d91cd9ac2	9ec742d0746f2bfe5096d55d91cd9ac2	le carbone	general	30	2025-11-21 01:16:51	2025-11-21 01:16:51
501	3	general_ai_d3356fa8322150b6010429d821eb9106	d3356fa8322150b6010429d821eb9106	axolotl	general	30	2025-11-21 01:17:10	2025-11-21 01:17:10
502	3	general_ai_cc99bc1615c6136a7939c264cfa4976c	cc99bc1615c6136a7939c264cfa4976c	la tortue,	general	30	2025-11-21 01:17:26	2025-11-21 01:17:26
503	3	general_ai_9b1356b0438d77c854311007feccfc3a	9b1356b0438d77c854311007feccfc3a	la seine	general	30	2025-11-21 01:17:49	2025-11-21 01:17:49
504	3	general_ai_6de0f8dba4f19209036291fab28983d0	6de0f8dba4f19209036291fab28983d0	monaco	general	30	2025-11-21 01:18:07	2025-11-21 01:18:07
505	3	general_ai_dece17954a0521b21ebf639f6c0447eb	dece17954a0521b21ebf639f6c0447eb	1955	general	30	2025-11-21 01:19:41	2025-11-21 01:19:41
506	3	general_ai_78d5ea1f96f6d855bce00d979280435e	78d5ea1f96f6d855bce00d979280435e	1942	general	30	2025-11-21 01:20:08	2025-11-21 01:20:08
507	3	general_ai_9fc0948e44848ec7922750c635998bd5	9fc0948e44848ec7922750c635998bd5	Égypte	general	30	2025-11-21 01:20:35	2025-11-21 01:20:35
508	3	general_ai_ff56ec756843c3e36a9bab09df99f3c9	ff56ec756843c3e36a9bab09df99f3c9	faux	general	30	2025-11-21 01:20:54	2025-11-21 01:20:54
509	3	general_ai_16912cad8b14378b63a55e10d4861e38	16912cad8b14378b63a55e10d4861e38	cuivre	general	30	2025-11-21 01:21:12	2025-11-21 01:21:12
510	3	general_ai_c341960b9d7e005956b8bcf41cc07299	c341960b9d7e005956b8bcf41cc07299	8849 mètres	general	30	2025-11-21 01:21:28	2025-11-21 01:21:28
511	3	general_ai_5a4530f2ca6d6262c03f7c94e24d0c11	5a4530f2ca6d6262c03f7c94e24d0c11	faux	general	30	2025-11-21 01:21:54	2025-11-21 01:21:54
512	3	general_ai_1d53911914aa62a0efb5ea7e3a9edf80	1d53911914aa62a0efb5ea7e3a9edf80	l'ornithorynque	general	30	2025-11-21 01:22:12	2025-11-21 01:22:12
513	3	general_ai_a893d1c788633bdae1fd921657fb2cf2	a893d1c788633bdae1fd921657fb2cf2	faux	general	30	2025-11-21 01:22:35	2025-11-21 01:22:35
514	3	general_ai_1cddad7a70e56e1ff00f4d048b799023	1cddad7a70e56e1ff00f4d048b799023	estonie	general	30	2025-11-21 01:22:54	2025-11-21 01:22:54
515	3	general_ai_0e70d2b56b035a1eaa2440ad7e7e53b6	0e70d2b56b035a1eaa2440ad7e7e53b6	bill gates	general	30	2025-11-21 01:23:24	2025-11-21 01:23:24
516	3	faune_ai_2c0fd6a7e5ad1a601151b620cb035157	2c0fd6a7e5ad1a601151b620cb035157	éléphant d'afrique	faune	10	2025-11-22 15:33:54	2025-11-22 15:33:54
517	3	faune_ai_a4f9dd5ae36f77b659dc1cc4adce00d7	a4f9dd5ae36f77b659dc1cc4adce00d7	caméléon	faune	10	2025-11-22 15:34:35	2025-11-22 15:34:35
518	3	faune_ai_1111d636052469b41a9c17ee2c844b66	1111d636052469b41a9c17ee2c844b66	la souris pygmée	faune	10	2025-11-22 15:35:07	2025-11-22 15:35:07
519	3	faune_ai_d07e84dc86f67f5fc539cbfde197d5b2	d07e84dc86f67f5fc539cbfde197d5b2	vrai	faune	10	2025-11-22 15:35:42	2025-11-22 15:35:42
520	3	faune_ai_b3a117dd4ec5c24ede1610bbd95cec70	b3a117dd4ec5c24ede1610bbd95cec70	faux	faune	10	2025-11-22 15:36:10	2025-11-22 15:36:10
521	3	faune_ai_92178c449268ea3349bcfc319d159e83	92178c449268ea3349bcfc319d159e83	caméléon	faune	10	2025-11-22 15:36:33	2025-11-22 15:36:33
522	3	faune_ai_e447274c9f87446aed370f5b057ab8ff	e447274c9f87446aed370f5b057ab8ff	aigle royal	faune	10	2025-11-22 15:37:05	2025-11-22 15:37:05
523	3	faune_ai_94857a7184b1128643f8e0aa65d1a9ec	94857a7184b1128643f8e0aa65d1a9ec	Éléphant	faune	10	2025-11-22 15:37:28	2025-11-22 15:37:28
524	3	faune_ai_c4af448fc46ee6dbb5ab0ad4e7ec1904	c4af448fc46ee6dbb5ab0ad4e7ec1904	la girafe	faune	10	2025-11-22 15:38:00	2025-11-22 15:38:00
525	3	faune_ai_afd9e3b51887a26131a028f4696a1a5b	afd9e3b51887a26131a028f4696a1a5b	colibri	faune	10	2025-11-22 15:38:26	2025-11-22 15:38:26
526	3	faune_ai_0de34a81dcad5eef12c8817986b12f27	0de34a81dcad5eef12c8817986b12f27	guépard	faune	10	2025-11-22 15:39:48	2025-11-22 15:39:48
527	3	faune_ai_98fbe7d888cda5512270f068882b9b4e	98fbe7d888cda5512270f068882b9b4e	thon	faune	10	2025-11-22 15:40:06	2025-11-22 15:40:06
528	3	faune_ai_c407a0a6b0d830854e8baa2d83dbf031	c407a0a6b0d830854e8baa2d83dbf031	caméléon	faune	10	2025-11-22 15:40:28	2025-11-22 15:40:28
529	3	faune_ai_01c942f3a20ee3afbc367d929a324967	01c942f3a20ee3afbc367d929a324967	faux	faune	10	2025-11-22 15:40:58	2025-11-22 15:40:58
530	3	faune_ai_ee748368eb3015dd67cb94bff31446d0	ee748368eb3015dd67cb94bff31446d0	la baleine bleue	faune	10	2025-11-22 15:41:20	2025-11-22 15:41:20
531	3	faune_ai_2c16c8c67b6bd00ebaf2065ad3e2eeef	2c16c8c67b6bd00ebaf2065ad3e2eeef	guépard	faune	10	2025-11-22 15:41:35	2025-11-22 15:41:35
532	3	faune_ai_5aa6cfeec9bd3ee46d2f099fe1a460f0	5aa6cfeec9bd3ee46d2f099fe1a460f0	le monarque	faune	10	2025-11-22 15:41:51	2025-11-22 15:41:51
533	3	faune_ai_0d22b959b8c8bb41b3641c496bbafd73	0d22b959b8c8bb41b3641c496bbafd73	la tortue des galápagos	faune	10	2025-11-22 15:42:20	2025-11-22 15:42:20
534	3	faune_ai_42af5b1238538d4d087ce0e74202697d	42af5b1238538d4d087ce0e74202697d	fennec	faune	10	2025-11-22 15:42:41	2025-11-22 15:42:41
535	3	faune_ai_8759e0ed2ba4077e090b1937a25ee780	8759e0ed2ba4077e090b1937a25ee780	le caribou	faune	10	2025-11-22 15:43:15	2025-11-22 15:43:15
536	3	general_ai_925cd29294500db3db3fb1bda888a47d	925cd29294500db3db3fb1bda888a47d	vrai	general	16	2025-11-22 23:05:32	2025-11-22 23:05:32
537	3	general_ai_0b992575e9d77b87bc417d094957d107	0b992575e9d77b87bc417d094957d107	japon	general	16	2025-11-22 23:06:05	2025-11-22 23:06:05
538	3	general_ai_b52b06f2ab11871847225b5aa5fab873	b52b06f2ab11871847225b5aa5fab873	mercure	general	16	2025-11-22 23:06:35	2025-11-22 23:06:35
539	3	general_ai_49bd70016464129129d95d4cdc270445	49bd70016464129129d95d4cdc270445	le colibri	general	16	2025-11-22 23:07:06	2025-11-22 23:07:06
540	3	general_ai_f70df46e5c5dfc6ef866ed67d81af645	f70df46e5c5dfc6ef866ed67d81af645	galdhøpiggen	general	16	2025-11-22 23:07:31	2025-11-22 23:07:31
541	3	general_ai_1f865500bfbd1ef65d7bb05aea54f3c4	1f865500bfbd1ef65d7bb05aea54f3c4	océan pacifique	general	16	2025-11-22 23:08:00	2025-11-22 23:08:00
542	3	general_ai_45bdc17cf9b7ee82ec4ebcde64241d32	45bdc17cf9b7ee82ec4ebcde64241d32	Éthiopie	general	16	2025-11-22 23:08:28	2025-11-22 23:08:28
543	3	general_ai_b20c77c8898d8cd5e406d7ef4cb1e2ea	b20c77c8898d8cd5e406d7ef4cb1e2ea	8849 mètres	general	16	2025-11-22 23:09:02	2025-11-22 23:09:02
544	3	general_ai_e0eceeb68027ca2e00905346ad619a2c	e0eceeb68027ca2e00905346ad619a2c	faux	general	16	2025-11-22 23:09:30	2025-11-22 23:09:30
545	3	general_ai_c1b7cbc1421bc439a04136b285ba550b	c1b7cbc1421bc439a04136b285ba550b	islande	general	16	2025-11-22 23:09:47	2025-11-22 23:09:47
546	3	general_ai_ebe466e54e261eaee503bbf2d7ecf3eb	ebe466e54e261eaee503bbf2d7ecf3eb	or	general	16	2025-11-22 23:11:31	2025-11-22 23:11:31
547	3	general_ai_e4903c704a5bda88dbdaa25503cb63d2	e4903c704a5bda88dbdaa25503cb63d2	apollo 11	general	16	2025-11-22 23:11:56	2025-11-22 23:11:56
548	3	general_ai_d0c11ab2484830d65cacbe49f98236ae	d0c11ab2484830d65cacbe49f98236ae	éléphant	general	16	2025-11-22 23:12:21	2025-11-22 23:12:21
549	3	general_ai_fdcc257c3d44980dc2aa43a1fe9d5e85	fdcc257c3d44980dc2aa43a1fe9d5e85	كتور	general	16	2025-11-22 23:12:41	2025-11-22 23:12:41
550	3	general_ai_2d616bc97dd28b19d829da32172d1f0b	2d616bc97dd28b19d829da32172d1f0b	amazone	general	16	2025-11-22 23:13:18	2025-11-22 23:13:18
551	3	general_ai_3ab40690038d17002322d3d06b94ae5f	3ab40690038d17002322d3d06b94ae5f	faux	general	16	2025-11-22 23:13:40	2025-11-22 23:13:40
552	3	general_ai_bded03ec3fcb2945672318b5d591f5d1	bded03ec3fcb2945672318b5d591f5d1	guépard	general	16	2025-11-22 23:13:58	2025-11-22 23:13:58
553	3	general_ai_1f841b42dd1170cefc2cafce80e81e83	1f841b42dd1170cefc2cafce80e81e83	léopard	general	16	2025-11-22 23:14:18	2025-11-22 23:14:18
554	3	general_ai_3ce59282fcad8e26442650dce9f7093d	3ce59282fcad8e26442650dce9f7093d	Éléphant d'afrique	general	16	2025-11-22 23:14:44	2025-11-22 23:14:44
555	3	general_ai_16ac6586fa8d8493e75a296b12af456c	16ac6586fa8d8493e75a296b12af456c	hélium	general	16	2025-11-22 23:15:18	2025-11-22 23:15:18
556	3	geographie_ai_55f24513a1ca60f81f417546bc31c519	55f24513a1ca60f81f417546bc31c519	antarctique	geographie	10	2025-11-23 00:56:28	2025-11-23 00:56:28
557	3	geographie_ai_65a4743c8f9e6743a4a543c5952785f8	65a4743c8f9e6743a4a543c5952785f8	algérie	geographie	10	2025-11-23 00:56:56	2025-11-23 00:56:56
558	3	geographie_ai_eab03235b531479f7c298710007d08c6	eab03235b531479f7c298710007d08c6	océan pacifique	geographie	10	2025-11-23 00:57:28	2025-11-23 00:57:28
559	3	geographie_ai_dac539c568cd4e05da7430e70a4427f3	dac539c568cd4e05da7430e70a4427f3	madagascar	geographie	10	2025-11-23 00:57:58	2025-11-23 00:57:58
560	3	geographie_ai_ac773494c7b93a4541ad8ada05a1fcd8	ac773494c7b93a4541ad8ada05a1fcd8	asie	geographie	10	2025-11-23 00:58:28	2025-11-23 00:58:28
561	3	geographie_ai_68300435f0c441e45504abb692538a42	68300435f0c441e45504abb692538a42	mont everest	geographie	10	2025-11-23 00:58:55	2025-11-23 00:58:55
562	3	geographie_ai_c01d5bbe5291f4189e825006460dbc0f	c01d5bbe5291f4189e825006460dbc0f	la mer morte	geographie	10	2025-11-23 00:59:19	2025-11-23 00:59:19
563	3	geographie_ai_fe326694f6e7c897a7761c37ddd5fd0a	fe326694f6e7c897a7761c37ddd5fd0a	russie	geographie	10	2025-11-23 00:59:53	2025-11-23 00:59:53
564	3	geographie_ai_5840e0ffb135fadac34abf16df44ebc4	5840e0ffb135fadac34abf16df44ebc4	mont everest	geographie	10	2025-11-23 01:00:22	2025-11-23 01:00:22
565	3	geographie_ai_1fa66d349c2749f97adcb9f8a7a89cd9	1fa66d349c2749f97adcb9f8a7a89cd9	afrique	geographie	10	2025-11-23 01:00:47	2025-11-23 01:00:47
566	3	general_ai_4b2072c243bfbdad51da80f0b8ba0bc5	4b2072c243bfbdad51da80f0b8ba0bc5	baleine bleue	general	5	2025-11-23 17:29:27	2025-11-23 17:29:27
567	3	general_ai_bd60a3a95436682c4d848f94ade672e4	bd60a3a95436682c4d848f94ade672e4	le faucon pélerin	general	5	2025-11-23 17:29:57	2025-11-23 17:29:57
568	3	general_ai_14afb2c793e31bcb09f516e4e8c06c4f	14afb2c793e31bcb09f516e4e8c06c4f	tortue	general	5	2025-11-23 17:31:12	2025-11-23 17:31:12
569	3	general_ai_0d189b2d99f2628451b42c7afa721dae	0d189b2d99f2628451b42c7afa721dae	vrai	general	5	2025-11-23 17:31:31	2025-11-23 17:31:31
570	3	general_ai_1093abe7d797e7dc7fe0cad57e408c58	1093abe7d797e7dc7fe0cad57e408c58	l'himalaya	general	5	2025-11-23 17:32:00	2025-11-23 17:32:00
571	3	general_ai_1b412e2b2e5bf3b17e9bb28d5a3cc234	1b412e2b2e5bf3b17e9bb28d5a3cc234	diamant	general	5	2025-11-23 17:32:28	2025-11-23 17:32:28
572	3	general_ai_457b6ef2dcaa35b4cce9ae250e9c59a1	457b6ef2dcaa35b4cce9ae250e9c59a1	la rose	general	5	2025-11-23 17:32:54	2025-11-23 17:32:54
573	3	general_ai_2fb3127f707e84d33adcfab2eaf13dea	2fb3127f707e84d33adcfab2eaf13dea	oxygène	general	5	2025-11-23 17:33:22	2025-11-23 17:33:22
574	3	general_ai_5c559abd400a150b6875a9317a184b92	5c559abd400a150b6875a9317a184b92	séquoia	general	5	2025-11-23 17:33:52	2025-11-23 17:33:52
575	3	general_ai_0daf57bd0a4a3a1d5fbc41f6117afdb8	0daf57bd0a4a3a1d5fbc41f6117afdb8	hippopotame	general	5	2025-11-23 17:34:20	2025-11-23 17:34:20
576	3	faune_ai_dbf1c2f57c2a8b0a50c3995d6ef4b93f	dbf1c2f57c2a8b0a50c3995d6ef4b93f	le cachalot	faune	5	2025-11-23 19:18:58	2025-11-23 19:18:58
577	3	faune_ai_2892003d94a3bfa7fdfc2dd27cfbec90	2892003d94a3bfa7fdfc2dd27cfbec90	le kangourou	faune	5	2025-11-23 19:19:29	2025-11-23 19:19:29
578	3	faune_ai_81400fc864822c299c7065a402c15805	81400fc864822c299c7065a402c15805	poisson	faune	5	2025-11-23 19:20:00	2025-11-23 19:20:00
579	3	faune_ai_c61b079e3e9f79729e9cc6892350fefc	c61b079e3e9f79729e9cc6892350fefc	koala	faune	5	2025-11-23 19:20:32	2025-11-23 19:20:32
580	3	faune_ai_625694e9f5d149101aa7b061cdbfbdbb	625694e9f5d149101aa7b061cdbfbdbb	le chien	faune	5	2025-11-23 19:21:04	2025-11-23 19:21:04
581	3	faune_ai_0bf1aa5641577f61e4f90419432cb72f	0bf1aa5641577f61e4f90419432cb72f	l'éléphant	faune	5	2025-11-23 19:21:35	2025-11-23 19:21:35
582	3	faune_ai_a27cb9c414227433aa306f219fa60d50	a27cb9c414227433aa306f219fa60d50	l'âne	faune	5	2025-11-23 19:22:11	2025-11-23 19:22:11
583	3	faune_ai_16ae737beddffba24d5000c6ab9d868c	16ae737beddffba24d5000c6ab9d868c	espadon	faune	5	2025-11-23 19:22:37	2025-11-23 19:22:37
584	3	faune_ai_6ec030c4a6917e5735dff8dc3d4f51de	6ec030c4a6917e5735dff8dc3d4f51de	éléphant d'afrique	faune	5	2025-11-23 19:23:03	2025-11-23 19:23:03
585	3	faune_ai_8f4d323da93052b534868f66a46b1b84	8f4d323da93052b534868f66a46b1b84	l'éléphant d'afrique	faune	5	2025-11-23 19:23:23	2025-11-23 19:23:23
586	3	faune_ai_bacca5fddb62ff561545660c67c0844b	bacca5fddb62ff561545660c67c0844b	la loutre	faune	5	2025-11-23 19:28:05	2025-11-23 19:28:05
587	3	faune_ai_47cfe5600a2707ca330917cf3e175f1a	47cfe5600a2707ca330917cf3e175f1a	la grenouille	faune	5	2025-11-23 19:28:35	2025-11-23 19:28:35
588	3	faune_ai_586fe1085464d9ce8c7231525b55fcca	586fe1085464d9ce8c7231525b55fcca	faux	faune	5	2025-11-23 19:28:58	2025-11-23 19:28:58
589	3	faune_ai_e3131516ce06232015d0665728657610	e3131516ce06232015d0665728657610	caméléon	faune	5	2025-11-23 19:29:12	2025-11-23 19:29:12
590	3	faune_ai_481742689458d031d9e33184eefe7e32	481742689458d031d9e33184eefe7e32	vrai	faune	5	2025-11-23 19:29:42	2025-11-23 19:29:42
591	3	faune_ai_a8b3b20c80665e4fba589631c90bff5e	a8b3b20c80665e4fba589631c90bff5e	mante religieuse	faune	5	2025-11-23 19:30:05	2025-11-23 19:30:05
592	3	faune_ai_c0504230b02c75f60d512bab74496886	c0504230b02c75f60d512bab74496886	le guépard	faune	5	2025-11-23 19:30:25	2025-11-23 19:30:25
593	3	faune_ai_eceea09aa9fca14224dff1abdc1a8758	eceea09aa9fca14224dff1abdc1a8758	guépard	faune	5	2025-11-23 19:30:46	2025-11-23 19:30:46
594	3	faune_ai_9c365657b2637db35b9e844b96d822d1	9c365657b2637db35b9e844b96d822d1	guépard	faune	5	2025-11-23 19:31:12	2025-11-23 19:31:12
595	3	faune_ai_8c8629540385cea3bb8b43b06e6ea1a7	8c8629540385cea3bb8b43b06e6ea1a7	le cachalot	faune	5	2025-11-23 19:31:42	2025-11-23 19:31:42
596	3	general_ai_13d7bac9daa88ae6d94bd615df343899	13d7bac9daa88ae6d94bd615df343899	8849 mètres	general	5	2025-11-23 20:10:32	2025-11-23 20:10:32
597	3	general_ai_8228dc79a187ab526e3c3661b81fc54e	8228dc79a187ab526e3c3661b81fc54e	guépard	general	5	2025-11-23 20:11:16	2025-11-23 20:11:16
598	3	general_ai_2b61858e4e98cc87689954ccca1707b2	2b61858e4e98cc87689954ccca1707b2	la grenouille	general	5	2025-11-23 20:11:46	2025-11-23 20:11:46
599	3	general_ai_3689ccef8347ad596086458b460c7ec5	3689ccef8347ad596086458b460c7ec5	colibri	general	5	2025-11-23 20:12:07	2025-11-23 20:12:07
600	3	general_ai_378d60f9b9dcc9674cc2f2cf23b6ee39	378d60f9b9dcc9674cc2f2cf23b6ee39	le nil	general	5	2025-11-23 20:12:25	2025-11-23 20:12:25
601	3	general_ai_04fc8a7ad30b4ec4620ad6253f3c19da	04fc8a7ad30b4ec4620ad6253f3c19da	faux	general	5	2025-11-23 20:12:58	2025-11-23 20:12:58
602	3	general_ai_f1ebd61759d7662edde1ddfb4d6b1dd0	f1ebd61759d7662edde1ddfb4d6b1dd0	la baleine bleue	general	5	2025-11-23 20:13:32	2025-11-23 20:13:32
603	3	general_ai_48e033634d95105c177e0c1ccfd4677c	48e033634d95105c177e0c1ccfd4677c	le kilauea	general	5	2025-11-23 20:14:02	2025-11-23 20:14:02
604	3	general_ai_542b42ac2aad25929b894a37a35e6646	542b42ac2aad25929b894a37a35e6646	océan pacifique	general	5	2025-11-23 20:14:19	2025-11-23 20:14:19
605	3	general_ai_f7eb11f77c38d1f7dd82376205e32dc9	f7eb11f77c38d1f7dd82376205e32dc9	kilauea	general	5	2025-11-23 20:14:37	2025-11-23 20:14:37
606	3	general_ai_4b0b857192231ce0a6a0ed0fafe6c7fa	4b0b857192231ce0a6a0ed0fafe6c7fa	faux	general	5	2025-11-23 20:15:04	2025-11-23 20:15:04
607	3	general_ai_bd5b82125c4d18327a68972e20c6240c	bd5b82125c4d18327a68972e20c6240c	vrai	general	5	2025-11-23 20:15:25	2025-11-23 20:15:25
608	3	general_ai_adba9cca7062ef9bc22c0f7131c9b0f5	adba9cca7062ef9bc22c0f7131c9b0f5	vrai	general	5	2025-11-23 20:15:41	2025-11-23 20:15:41
609	3	general_ai_11d6afe26f61ac02487cdda78324cd8a	11d6afe26f61ac02487cdda78324cd8a	mont everest	general	5	2025-11-23 20:15:59	2025-11-23 20:15:59
610	3	general_ai_2e6da9a5acb84f3be719078d560ac4dc	2e6da9a5acb84f3be719078d560ac4dc	guépard	general	5	2025-11-23 20:16:19	2025-11-23 20:16:19
611	3	general_ai_411a48602c2b4e141c1f11c414ea2242	411a48602c2b4e141c1f11c414ea2242	caméléon	general	5	2025-11-23 20:16:36	2025-11-23 20:16:36
612	3	general_ai_4ffa2ead28d49bb1e67e5bd22bba3609	4ffa2ead28d49bb1e67e5bd22bba3609	dauphin	general	5	2025-11-23 20:17:05	2025-11-23 20:17:05
613	3	general_ai_29f15d1f4bb42d53fe006eac055fd8f4	29f15d1f4bb42d53fe006eac055fd8f4	l'ornithorynque	general	5	2025-11-23 20:17:28	2025-11-23 20:17:28
614	3	general_ai_0cbc1f5f61f95916c2ffc3ea5115288a	0cbc1f5f61f95916c2ffc3ea5115288a	requin baleine	general	5	2025-11-23 20:17:59	2025-11-23 20:17:59
615	3	general_ai_5b867948acf0fbfbd4ddb13c6d66e7f6	5b867948acf0fbfbd4ddb13c6d66e7f6	le colibri	general	5	2025-11-23 20:18:20	2025-11-23 20:18:20
616	3	general_ai_b6dc3a274e54addc85b624abcfeba1ec	b6dc3a274e54addc85b624abcfeba1ec	girafe	general	5	2025-11-23 20:50:23	2025-11-23 20:50:23
617	3	general_ai_a7da6bcda6d9ae4c164e095b326a7752	a7da6bcda6d9ae4c164e095b326a7752	requin	general	5	2025-11-23 20:50:47	2025-11-23 20:50:47
618	3	general_ai_e5c35651034bbbb290d7e4b6ed517664	e5c35651034bbbb290d7e4b6ed517664	faux	general	5	2025-11-23 20:51:17	2025-11-23 20:51:17
619	3	general_ai_1b8e32456fdaaef28f9e3bf33ac836a7	1b8e32456fdaaef28f9e3bf33ac836a7	guépard	general	5	2025-11-23 20:51:47	2025-11-23 20:51:47
620	3	general_ai_75613c5b6da8888c6d903d458e0e7272	75613c5b6da8888c6d903d458e0e7272	océan pacifique	general	5	2025-11-23 20:52:19	2025-11-23 20:52:19
621	3	general_ai_dd633a1a4275968c997b17d907a35742	dd633a1a4275968c997b17d907a35742	l'écureuil volant	general	5	2025-11-23 20:52:40	2025-11-23 20:52:40
622	3	general_ai_3423f5d925b146de0ea0ad6e87bf15b3	3423f5d925b146de0ea0ad6e87bf15b3	10994 mètres	general	5	2025-11-23 20:53:14	2025-11-23 20:53:14
623	3	general_ai_41ac61b36be48244d42a57ab61620a2b	41ac61b36be48244d42a57ab61620a2b	vrai	general	5	2025-11-23 20:53:40	2025-11-23 20:53:40
624	3	general_ai_a10a0966f97ecd64e5621d4225c140c2	a10a0966f97ecd64e5621d4225c140c2	faux	general	5	2025-11-23 20:54:04	2025-11-23 20:54:04
625	3	general_ai_8102b98ae97b2f2a45644631682c313d	8102b98ae97b2f2a45644631682c313d	tortue de mer	general	5	2025-11-23 20:54:25	2025-11-23 20:54:25
626	3	sport_ai_c5ae784b3256aa0643c50a64b298fe62	c5ae784b3256aa0643c50a64b298fe62	roger federer	sport	5	2025-11-23 21:10:38	2025-11-23 21:10:38
627	3	sport_ai_a5f3d9125984d5f558a74e709e477c15	a5f3d9125984d5f558a74e709e477c15	le skateboard	sport	5	2025-11-23 21:11:12	2025-11-23 21:11:12
628	3	sport_ai_02002092fd2131cd1ec172cabc5325d3	02002092fd2131cd1ec172cabc5325d3	marta cotrani	sport	5	2025-11-23 21:11:45	2025-11-23 21:11:45
629	3	sport_ai_4e732e3f0e054e678ef302e723ea3742	4e732e3f0e054e678ef302e723ea3742	angleterre	sport	5	2025-11-23 21:12:17	2025-11-23 21:12:17
630	3	sport_ai_108888542988b868ceb1b2914e5cebc0	108888542988b868ceb1b2914e5cebc0	vrai	sport	5	2025-11-23 21:12:40	2025-11-23 21:12:40
631	3	sport_ai_837ab83116e1030f0fc3ea768f78aab9	837ab83116e1030f0fc3ea768f78aab9	athènes	sport	5	2025-11-23 21:13:04	2025-11-23 21:13:04
632	3	sport_ai_854bfc2b691b0abf9f55cf11618a259d	854bfc2b691b0abf9f55cf11618a259d	marta	sport	5	2025-11-23 21:13:33	2025-11-23 21:13:33
633	3	sport_ai_7714978182d2d3cc2643448aa71f89e7	7714978182d2d3cc2643448aa71f89e7	brésil	sport	5	2025-11-23 21:14:07	2025-11-23 21:14:07
634	3	sport_ai_8b3c077374e382b42746878b054d2c6b	8b3c077374e382b42746878b054d2c6b	hockey sur glace	sport	5	2025-11-23 21:14:45	2025-11-23 21:14:45
635	3	sport_ai_2bc279ae8d960c968c5af402ee86d402	2bc279ae8d960c968c5af402ee86d402	grèce	sport	5	2025-11-23 21:15:12	2025-11-23 21:15:12
636	3	geographie_ai_5f8bc8f621b03677152802d792a126d4	5f8bc8f621b03677152802d792a126d4	groenland	geographie	47	2025-11-23 21:22:08	2025-11-23 21:22:08
637	3	geographie_ai_1e07973de01de7c55d808403d4aa61d8	1e07973de01de7c55d808403d4aa61d8	mer morte	geographie	47	2025-11-23 21:22:41	2025-11-23 21:22:41
638	3	geographie_ai_8df123b0800d41d547f49e46c8ea864f	8df123b0800d41d547f49e46c8ea864f	désert du sahara	geographie	47	2025-11-23 21:23:15	2025-11-23 21:23:15
639	3	geographie_ai_5a5edf9b6b16e02fee796ca6cba4f505	5a5edf9b6b16e02fee796ca6cba4f505	nil	geographie	47	2025-11-23 21:23:39	2025-11-23 21:23:39
640	3	geographie_ai_eecb5986f271e1bf13fdcd1abd9c2efe	eecb5986f271e1bf13fdcd1abd9c2efe	italie	geographie	47	2025-11-23 21:23:57	2025-11-23 21:23:57
641	3	geographie_ai_f63e2c0f7f2d43cb693d49302a46558e	f63e2c0f7f2d43cb693d49302a46558e	nil	geographie	47	2025-11-23 21:24:18	2025-11-23 21:24:18
642	3	geographie_ai_776aa0a415993a5028ec72dc9077b139	776aa0a415993a5028ec72dc9077b139	groenland	geographie	47	2025-11-23 21:24:49	2025-11-23 21:24:49
643	3	geographie_ai_110d00b7cda3956c12c6fd536075bbf7	110d00b7cda3956c12c6fd536075bbf7	rio negro	geographie	47	2025-11-23 21:25:08	2025-11-23 21:25:08
644	3	geographie_ai_a292672ae8fd23ccc9f654a3fbebf085	a292672ae8fd23ccc9f654a3fbebf085	la mer morte	geographie	47	2025-11-23 21:25:47	2025-11-23 21:25:47
645	3	geographie_ai_c2fccff9566628f1dac2ee9e781c7c81	c2fccff9566628f1dac2ee9e781c7c81	amazone	geographie	47	2025-11-23 21:26:23	2025-11-23 21:26:23
646	3	geographie_ai_00b30c40c1118f812612092c6e42be23	00b30c40c1118f812612092c6e42be23	antarctic desert	geographie	10	2025-11-26 18:09:15	2025-11-26 18:09:15
647	3	geographie_ai_b840251e01dcba5a72548e6a7df36c09	b840251e01dcba5a72548e6a7df36c09	ural mountains	geographie	10	2025-11-26 18:09:42	2025-11-26 18:09:42
648	3	geographie_ai_163cd57ad52435dc6a1e43a9b345b41f	163cd57ad52435dc6a1e43a9b345b41f	vrai	geographie	10	2025-11-26 18:10:10	2025-11-26 18:10:10
649	3	geographie_ai_17c4a26a3959261bc626bf51da500ffa	17c4a26a3959261bc626bf51da500ffa	antarctica	geographie	10	2025-11-26 18:10:44	2025-11-26 18:10:44
650	3	geographie_ai_03688795061a47f86ed8f41234e71537	03688795061a47f86ed8f41234e71537	ethiopia	geographie	10	2025-11-26 18:11:16	2025-11-26 18:11:16
651	3	geographie_ai_8b01f8429f082d16c5cc90fa46b15051	8b01f8429f082d16c5cc90fa46b15051	mount everest	geographie	10	2025-11-26 18:11:44	2025-11-26 18:11:44
652	3	geographie_ai_c8e4abea0dc0d93274a8ff95ae3184a3	c8e4abea0dc0d93274a8ff95ae3184a3	antarctic desert	geographie	10	2025-11-26 18:12:06	2025-11-26 18:12:06
653	3	geographie_ai_8f5fd813c19aa1ce67440853a56a43e9	8f5fd813c19aa1ce67440853a56a43e9	nile	geographie	10	2025-11-26 18:12:25	2025-11-26 18:12:25
654	3	geographie_ai_3a3b94446c89c44f43838c2049b50bc5	3a3b94446c89c44f43838c2049b50bc5	amazon river	geographie	10	2025-11-26 18:12:42	2025-11-26 18:12:42
655	3	geographie_ai_18a6e35b962869cd5e3ccfc563b3027a	18a6e35b962869cd5e3ccfc563b3027a	90° n	geographie	10	2025-11-26 18:13:01	2025-11-26 18:13:01
656	3	faune_ai_7f50dfedb1ae2bd4e353a9d2dc0456f5	7f50dfedb1ae2bd4e353a9d2dc0456f5	grenouille	faune	17	2025-11-27 17:29:55	2025-11-27 17:29:55
657	3	faune_ai_3c334de5c981b0e601458704fa7ac232	3c334de5c981b0e601458704fa7ac232	vrai	faune	17	2025-11-27 17:30:20	2025-11-27 17:30:20
658	3	faune_ai_59adb12ec68b8f385e6a4924360cdd19	59adb12ec68b8f385e6a4924360cdd19	l'ornithorynque	faune	17	2025-11-27 17:31:24	2025-11-27 17:31:24
659	3	faune_ai_d61c59365332c7b03736f8547e303199	d61c59365332c7b03736f8547e303199	le kangourou	faune	17	2025-11-27 17:32:28	2025-11-27 17:32:28
660	3	faune_ai_c6aae53b76a7d246cb3aec52b2c3132e	c6aae53b76a7d246cb3aec52b2c3132e	caméléon	faune	17	2025-11-27 17:33:36	2025-11-27 17:33:36
661	3	faune_ai_10b5d6829fbd83021e8eae75d1fa2774	10b5d6829fbd83021e8eae75d1fa2774	l'éléphant	faune	17	2025-11-27 17:35:35	2025-11-27 17:35:35
662	3	faune_ai_81e0ae549a45c52e6affba3ac62ab811	81e0ae549a45c52e6affba3ac62ab811	l'écureuil volant	faune	17	2025-11-27 17:37:51	2025-11-27 17:37:51
663	3	faune_ai_1510a88eb6f2504957f6786123512fff	1510a88eb6f2504957f6786123512fff	le tatou	faune	17	2025-11-27 17:39:45	2025-11-27 17:39:45
664	3	faune_ai_09188b4e5d056962f06e03beb270b736	09188b4e5d056962f06e03beb270b736	la chauve-souris	faune	17	2025-11-27 17:42:28	2025-11-27 17:42:28
665	3	faune_ai_1dad6370e72aaf92bc794b5c6567357a	1dad6370e72aaf92bc794b5c6567357a	le capucin	faune	17	2025-11-27 17:45:51	2025-11-27 17:45:51
666	3	general_ai_6c8ab37e2819442caae04ac3c5aed770	6c8ab37e2819442caae04ac3c5aed770	la mer morte	general	16	2025-11-28 16:21:38	2025-11-28 16:21:38
667	3	general_ai_d39eff6c3c7e19b7cca0bdc53b26a10c	d39eff6c3c7e19b7cca0bdc53b26a10c	faux	general	16	2025-11-28 16:22:44	2025-11-28 16:22:44
668	3	general_ai_9eade798f5d54e78f9399e9be950f48c	9eade798f5d54e78f9399e9be950f48c	la nouvelle-zélande	general	16	2025-11-28 16:23:19	2025-11-28 16:23:19
669	3	general_ai_058b0966b2f357dd2a68fd5414d53f00	058b0966b2f357dd2a68fd5414d53f00	faux	general	16	2025-11-28 16:23:23	2025-11-28 16:23:23
670	3	general_ai_1fff18b67486ac129b74c81054277ebd	1fff18b67486ac129b74c81054277ebd	5895 mètres	general	16	2025-11-28 16:23:55	2025-11-28 16:23:55
671	6	general_ai_ca307b9026e827b0489577322e773870	ca307b9026e827b0489577322e773870	la grenouille	general	2	2025-12-02 17:55:18	2025-12-02 17:55:18
672	6	general_ai_4c7a1c13a1450f7c6b3300c635e8e3fe	4c7a1c13a1450f7c6b3300c635e8e3fe	le merle	general	2	2025-12-02 17:55:50	2025-12-02 17:55:50
673	6	general_ai_44740a7aba8a8e3fa6a109dc2ffd70da	44740a7aba8a8e3fa6a109dc2ffd70da	faux	general	2	2025-12-02 17:56:23	2025-12-02 17:56:23
674	6	general_ai_3399ee831f3716343b175056ae1bc012	3399ee831f3716343b175056ae1bc012	guépard	general	2	2025-12-02 17:56:43	2025-12-02 17:56:43
675	6	general_ai_f03ee883c9cd6e3816829639bf87878f	f03ee883c9cd6e3816829639bf87878f	vrai	general	2	2025-12-02 17:57:13	2025-12-02 17:57:13
676	6	general_ai_53d5990c0dcbe145cd3a9f5a1d6c8e77	53d5990c0dcbe145cd3a9f5a1d6c8e77	8849 mètres	general	2	2025-12-02 17:57:47	2025-12-02 17:57:47
677	6	general_ai_b52c0b5d4bd682d05575232710ba02f7	b52c0b5d4bd682d05575232710ba02f7	océan pacifique	general	2	2025-12-02 17:58:15	2025-12-02 17:58:15
678	6	general_ai_889d8fe2df103c420344ee5c1baad654	889d8fe2df103c420344ee5c1baad654	faux	general	2	2025-12-02 17:58:47	2025-12-02 17:58:47
679	6	general_ai_0db3624906ac5c7a49c89488b45cf755	0db3624906ac5c7a49c89488b45cf755	la fougère	general	2	2025-12-02 17:59:16	2025-12-02 17:59:16
680	6	general_ai_006d708532291319e510815e753e976b	006d708532291319e510815e753e976b	faux	general	2	2025-12-02 17:59:51	2025-12-02 17:59:51
681	3	general_ai_55320d1714a0a9125461013361d997a8	55320d1714a0a9125461013361d997a8	l'autruche	general	7	2025-12-07 11:07:21	2025-12-07 11:07:21
682	3	general_ai_4ae81b36ac8a251c86a777e80463b75c	4ae81b36ac8a251c86a777e80463b75c	le koala	general	7	2025-12-07 11:07:52	2025-12-07 11:07:52
683	3	general_ai_e8618556e6731a355bb156670cb936ca	e8618556e6731a355bb156670cb936ca	le chêne	general	7	2025-12-07 11:08:21	2025-12-07 11:08:21
684	3	general_ai_849f9ea60c62adfe5bbd7d6cbabd87fb	849f9ea60c62adfe5bbd7d6cbabd87fb	l'éléphante	general	5	2025-12-10 15:01:42	2025-12-10 15:01:42
685	3	general_ai_9efb9f9837a30a371143e49ce44d0a72	9efb9f9837a30a371143e49ce44d0a72	le rouge-gorge	general	5	2025-12-10 15:02:09	2025-12-10 15:02:09
686	3	general_ai_f2fe6a8e0820aa61f71fc6fc2c94c9a6	f2fe6a8e0820aa61f71fc6fc2c94c9a6	le requin	general	5	2025-12-10 16:09:55	2025-12-10 16:09:55
687	3	general_ai_73b5b63081d392061069bd9074fbdce9	73b5b63081d392061069bd9074fbdce9	la hyène	general	5	2025-12-10 16:36:39	2025-12-10 16:36:39
688	3	general_ai_c652d151bb9e1ad1aba5ec66c68b89a8	c652d151bb9e1ad1aba5ec66c68b89a8	le poisson-zèbre	general	5	2025-12-10 17:08:04	2025-12-10 17:08:04
689	3	general_ai_bd8d090668aa7f50a998a95fb55dc63c	bd8d090668aa7f50a998a95fb55dc63c	l'éléphante	general	5	2025-12-10 17:08:20	2025-12-10 17:08:20
690	3	general_ai_47bc2fb6f090140c3be078d976c6652e	47bc2fb6f090140c3be078d976c6652e	ozone	general	47	2025-12-11 04:13:37	2025-12-11 04:13:37
691	3	general_ai_aff00e40104bc383ad736b3428796a88	aff00e40104bc383ad736b3428796a88	Étymologie	general	49	2025-12-11 04:16:13	2025-12-11 04:16:13
692	3	general_ai_9a4460595a579f8948a8846a904acc29	9a4460595a579f8948a8846a904acc29	blaise pascal	general	49	2025-12-11 04:16:42	2025-12-11 04:16:42
693	3	general_ai_43b387b79b2c3f1e343f8e8833159e3a	43b387b79b2c3f1e343f8e8833159e3a	l'arno	general	49	2025-12-11 04:17:17	2025-12-11 04:17:17
694	3	general_ai_fe9903d11d8f9314f36b9226e41e2363	fe9903d11d8f9314f36b9226e41e2363	alexander fleming	general	49	2025-12-11 04:17:50	2025-12-11 04:17:50
695	3	general_ai_db20a082f3336ebf340d2e3ecd7ca139	db20a082f3336ebf340d2e3ecd7ca139	sensorama	general	49	2025-12-11 04:18:30	2025-12-11 04:18:30
696	3	general_ai_00f5833aece3f1544bae42f9289a41e3	00f5833aece3f1544bae42f9289a41e3	le world wide web	general	49	2025-12-11 04:19:08	2025-12-11 04:19:08
697	3	general_ai_a47c24c13d642dbf797ac01b466d7428	a47c24c13d642dbf797ac01b466d7428	general motors	general	49	2025-12-11 04:19:41	2025-12-11 04:19:41
698	3	general_ai_b9f125cb8c66e544d084294b6a419744	b9f125cb8c66e544d084294b6a419744	la fusion sélective par laser (slm)	general	49	2025-12-11 04:20:20	2025-12-11 04:20:20
699	3	general_ai_41eb9384cafc708dbc88e40db8377ae0	41eb9384cafc708dbc88e40db8377ae0	titan	general	49	2025-12-11 04:20:51	2025-12-11 04:20:51
700	3	general_ai_009c53df22903f6ecff6f72eaf17d968	009c53df22903f6ecff6f72eaf17d968	vrai	general	49	2025-12-11 04:21:30	2025-12-11 04:21:30
701	3	general_ai_74ed783d06816103d0f12504d5b9cec3	74ed783d06816103d0f12504d5b9cec3	vrai	general	49	2025-12-11 04:25:49	2025-12-11 04:25:49
702	3	general_ai_34396d5939e2f8ef3a8cb773bd8dcae8	34396d5939e2f8ef3a8cb773bd8dcae8	johannes kepler	general	49	2025-12-11 04:26:24	2025-12-11 04:26:24
703	3	general_ai_bb8e26e410714ec1d6a92e33ffd4a0b2	bb8e26e410714ec1d6a92e33ffd4a0b2	vrai	general	49	2025-12-11 04:26:55	2025-12-11 04:26:55
704	3	general_ai_5fb3f91be144d8285faa1758e9f34e07	5fb3f91be144d8285faa1758e9f34e07	faux	general	49	2025-12-11 04:27:35	2025-12-11 04:27:35
705	3	general_ai_a98e4480055c80fa678da34c6e48dba8	a98e4480055c80fa678da34c6e48dba8	faux	general	49	2025-12-11 04:28:03	2025-12-11 04:28:03
706	3	general_ai_9a329a39ef9b60b5389b3f4fc4024f9c	9a329a39ef9b60b5389b3f4fc4024f9c	nirvana	general	49	2025-12-11 04:28:35	2025-12-11 04:28:35
707	3	general_ai_75e04e4c1921a206e316d65d1b22a948	75e04e4c1921a206e316d65d1b22a948	apple inc.	general	49	2025-12-11 04:29:09	2025-12-11 04:29:09
708	3	general_ai_59a3c3f4e04bdefac222fe96bfeedb24	59a3c3f4e04bdefac222fe96bfeedb24	vrai	general	49	2025-12-11 04:29:41	2025-12-11 04:29:41
709	3	general_ai_776a699f88baecfcdc52464293588069	776a699f88baecfcdc52464293588069	hémoglobine	general	49	2025-12-11 04:30:07	2025-12-11 04:30:07
710	3	general_ai_7a3c95e2357c94cba1c6a1ff09355714	7a3c95e2357c94cba1c6a1ff09355714	léonard de vinci	general	49	2025-12-11 04:30:44	2025-12-11 04:30:44
711	3	general_ai_41531c3bb32f29cd3924014f72b5d778	41531c3bb32f29cd3924014f72b5d778	vrai	general	4	2025-12-14 19:18:30	2025-12-14 19:18:30
712	3	general_ai_81bbefe0ddb6207a28f496fcad7e71b3	81bbefe0ddb6207a28f496fcad7e71b3	le lac de bled	general	59	2025-12-16 06:07:36	2025-12-16 06:07:36
713	3	general_ai_20cee936673f8a38aaee20f1a98b573e	20cee936673f8a38aaee20f1a98b573e	louis xiv	general	59	2025-12-16 06:08:11	2025-12-16 06:08:11
714	3	general_ai_953afeb79e1ef13b93b10b973319db4f	953afeb79e1ef13b93b10b973319db4f	vrai	general	59	2025-12-16 06:08:42	2025-12-16 06:08:42
715	3	general_ai_dfe9013e9b8fe952d713aa42be10e6a7	dfe9013e9b8fe952d713aa42be10e6a7	le danube	general	59	2025-12-16 06:09:14	2025-12-16 06:09:14
716	3	general_ai_9fff46b1872798a92fe6ea23bded527d	9fff46b1872798a92fe6ea23bded527d	protocole de kyoto	general	59	2025-12-16 06:09:44	2025-12-16 06:09:44
717	3	general_ai_2330b52c540db64e1b6afee3cd141e31	2330b52c540db64e1b6afee3cd141e31	faux	general	59	2025-12-16 06:13:01	2025-12-16 06:13:01
718	3	general_ai_c7f3d71ff1bc8679be09b2e2640f7c1d	c7f3d71ff1bc8679be09b2e2640f7c1d	traité de rome	general	59	2025-12-16 06:13:29	2025-12-16 06:13:29
719	3	general_ai_3335c4b8a2d82de9f87a5ee3e38ed4a8	3335c4b8a2d82de9f87a5ee3e38ed4a8	accord de paris	general	59	2025-12-16 06:14:03	2025-12-16 06:14:03
720	3	general_ai_5bded25c36b7c1a5765cb87e679e7ae7	5bded25c36b7c1a5765cb87e679e7ae7	l'azote	general	59	2025-12-16 06:14:40	2025-12-16 06:14:40
721	3	general_ai_e0ea0e948524370ea843913f6d445bc6	e0ea0e948524370ea843913f6d445bc6	l'affaire des fiches	general	59	2025-12-16 06:15:15	2025-12-16 06:15:15
722	3	general_ai_df89021b69b3565927c93fb495fe87b7	df89021b69b3565927c93fb495fe87b7	le lynx boréal	general	59	2025-12-16 06:16:21	2025-12-16 06:16:21
723	3	general_ai_b07d6e1f7102f715400a9635d632ecc9	b07d6e1f7102f715400a9635d632ecc9	le crocodile marin	general	59	2025-12-16 06:16:58	2025-12-16 06:16:58
724	3	general_ai_069153b4bf51a8072a6abfe9f1480da3	069153b4bf51a8072a6abfe9f1480da3	le phalanger renard	general	59	2025-12-16 06:17:30	2025-12-16 06:17:30
725	3	general_ai_1ef77fb74b5b4d631b5835bb772d8272	1ef77fb74b5b4d631b5835bb772d8272	l'orque	general	59	2025-12-16 06:18:06	2025-12-16 06:18:06
726	3	general_ai_fa48ad2505d72337aeed70368698c9de	fa48ad2505d72337aeed70368698c9de	la dynastie sui	general	59	2025-12-16 06:18:45	2025-12-16 06:18:45
727	3	general_ai_cfe972f2e4abca1047013841b0722a75	cfe972f2e4abca1047013841b0722a75	charles vii	general	59	2025-12-16 06:19:19	2025-12-16 06:19:19
728	3	general_ai_7316b0f8316de49cbf0019828bab20f1	7316b0f8316de49cbf0019828bab20f1	georg friedrich haendel	general	59	2025-12-16 06:20:03	2025-12-16 06:20:03
729	3	general_ai_b04e82beb78320b039e49fb93ccc23db	b04e82beb78320b039e49fb93ccc23db	le protocole de montréal	general	59	2025-12-16 06:20:41	2025-12-16 06:20:41
730	3	general_ai_28e7ba1bb2a83208fac55c9bd00742c4	28e7ba1bb2a83208fac55c9bd00742c4	l'equal employment opportunity commission (eeoc)	general	59	2025-12-16 06:21:25	2025-12-16 06:21:25
731	3	general_ai_f89b0bcf109813f1b1d0488c1f2660b1	f89b0bcf109813f1b1d0488c1f2660b1	john rawls	general	59	2025-12-16 06:22:10	2025-12-16 06:22:10
732	3	general_ai_d2c9f84773d2890dd23b9dd19c7fa11a	d2c9f84773d2890dd23b9dd19c7fa11a	l'arno	general	59	2025-12-16 07:38:11	2025-12-16 07:38:11
733	3	general_ai_30bcefed35f2e92f718d5fbde2c57646	30bcefed35f2e92f718d5fbde2c57646	sui	general	59	2025-12-16 07:38:44	2025-12-16 07:38:44
734	3	general_ai_401efff85a0c9a4b8629d479a0239405	401efff85a0c9a4b8629d479a0239405	la chine	general	59	2025-12-16 07:39:18	2025-12-16 07:39:18
735	3	general_ai_38c9b76b588ec16748c97836dabcc99c	38c9b76b588ec16748c97836dabcc99c	la lazurite	general	59	2025-12-16 07:39:59	2025-12-16 07:39:59
736	3	general_ai_914882d9b8abe0dfe0e0ad17d45f54b4	914882d9b8abe0dfe0e0ad17d45f54b4	le termite	general	59	2025-12-16 07:40:41	2025-12-16 07:40:41
737	3	general_ai_2880dba9c173b4ced5e9a636588ee003	2880dba9c173b4ced5e9a636588ee003	iqaluit	general	59	2025-12-16 07:41:20	2025-12-16 07:41:20
738	3	general_ai_d5da72e664c437de1ac1b49ff188dead	d5da72e664c437de1ac1b49ff188dead	faux	general	16	2025-12-16 08:30:06	2025-12-16 08:30:06
739	3	general_ai_5822ac267a01f495d615a1c22c599c5d	5822ac267a01f495d615a1c22c599c5d	charles darwin	general	59	2025-12-16 08:31:09	2025-12-16 08:31:09
740	3	general_ai_44647f2787c553177ef4cad95a34395c	44647f2787c553177ef4cad95a34395c	traités de westphalie	general	59	2025-12-16 08:31:48	2025-12-16 08:31:48
741	3	general_ai_790c76bc8db545f9c985e7391a6cb3d3	790c76bc8db545f9c985e7391a6cb3d3	faux	general	10	2025-12-16 09:09:20	2025-12-16 09:09:20
742	3	general_ai_cfa2fdd343ef5db748cc7e37cf707b77	cfa2fdd343ef5db748cc7e37cf707b77	le monarque	general	59	2025-12-16 09:10:53	2025-12-16 09:10:53
743	3	general_ai_e221834edf575d031f0c020a1c48b340	e221834edf575d031f0c020a1c48b340	ernest shackleton	general	59	2025-12-16 09:11:33	2025-12-16 09:11:33
744	3	general_ai_75bbe6ca889da2240ec37b64b4db14e0	75bbe6ca889da2240ec37b64b4db14e0	marc chagall	general	59	2025-12-16 09:12:08	2025-12-16 09:12:08
745	3	general_ai_22d5b2f71d309fda14cf7b08b56d7feb	22d5b2f71d309fda14cf7b08b56d7feb	la chlorophylle	general	59	2025-12-16 09:12:47	2025-12-16 09:12:47
746	10	general_ai_1d3073fdfa6727fdaacaa6505be88e61	1d3073fdfa6727fdaacaa6505be88e61	faux	general	1	2025-12-19 22:15:43	2025-12-19 22:15:43
747	10	general_ai_cf48efa6b0500b7110d9d9aec7785303	cf48efa6b0500b7110d9d9aec7785303	l'italie	general	1	2025-12-19 22:16:07	2025-12-19 22:16:07
748	10	general_ai_935b2dda591e962fe76dd3e29e444861	935b2dda591e962fe76dd3e29e444861	budapest	general	1	2025-12-19 22:16:29	2025-12-19 22:16:29
749	10	general_ai_3b830ce24cecc2c4d3225896df257f45	3b830ce24cecc2c4d3225896df257f45	vrai	general	1	2025-12-19 22:17:07	2025-12-19 22:17:07
750	10	general_ai_f01e5af61d4be7f2b3b43836ff186aac	f01e5af61d4be7f2b3b43836ff186aac	l'espagnol	general	1	2025-12-19 22:17:28	2025-12-19 22:17:28
751	10	general_ai_6891fa70a171d0f70b8b526b6a419e62	6891fa70a171d0f70b8b526b6a419e62	canberra	general	1	2025-12-19 22:17:53	2025-12-19 22:17:53
752	10	general_ai_32a4a3fa390ce5108ccc0a2cf8bee4f2	32a4a3fa390ce5108ccc0a2cf8bee4f2	porto	general	1	2025-12-19 22:18:16	2025-12-19 22:18:16
753	10	general_ai_57c700066d0dc99e6c94d20b732c0c7d	57c700066d0dc99e6c94d20b732c0c7d	l'autriche	general	1	2025-12-19 22:18:40	2025-12-19 22:18:40
754	10	general_ai_17653465d7ba35a27b455d8363747836	17653465d7ba35a27b455d8363747836	new york	general	1	2025-12-19 22:19:08	2025-12-19 22:19:08
755	10	general_ai_045d779c68e7fa1273f77de396684fa2	045d779c68e7fa1273f77de396684fa2	florence	general	1	2025-12-19 22:19:29	2025-12-19 22:19:29
756	10	general_ai_125719dda3ff3268b29158c03363d8ca	125719dda3ff3268b29158c03363d8ca	faux	general	1	2025-12-19 22:20:12	2025-12-19 22:20:12
757	10	general_ai_9f457c53cd81f9272e55e89e1541650a	9f457c53cd81f9272e55e89e1541650a	le tigre	general	1	2025-12-19 22:20:30	2025-12-19 22:20:30
758	10	general_ai_4bdf972d47199a109625e2c5aadc0dc2	4bdf972d47199a109625e2c5aadc0dc2	la marmotte	general	1	2025-12-19 22:20:47	2025-12-19 22:20:47
759	10	general_ai_76f5d8a9d0c7d7929bc262faaf3f9a4e	76f5d8a9d0c7d7929bc262faaf3f9a4e	le loir	general	1	2025-12-19 22:21:08	2025-12-19 22:21:08
760	10	general_ai_36cf2975f53c10d1a73ad02cf641632d	36cf2975f53c10d1a73ad02cf641632d	l'autruche	general	1	2025-12-19 22:21:45	2025-12-19 22:21:45
761	10	general_ai_08a3a92cfe3fc6f5a7331c0accac1523	08a3a92cfe3fc6f5a7331c0accac1523	le paresseux	general	1	2025-12-19 22:22:21	2025-12-19 22:22:21
762	10	general_ai_0495237df9ced21e6d5ca3d10e1b11b0	0495237df9ced21e6d5ca3d10e1b11b0	vrai	general	1	2025-12-19 22:22:54	2025-12-19 22:22:54
763	10	general_ai_0a8c2cbadb3b39a802975f842929a067	0a8c2cbadb3b39a802975f842929a067	le hérisson	general	1	2025-12-19 22:23:15	2025-12-19 22:23:15
764	10	general_ai_002dd43023f626cd3d309bb512357ee4	002dd43023f626cd3d309bb512357ee4	le zèbre	general	1	2025-12-19 22:23:48	2025-12-19 22:23:48
765	10	general_ai_70d3b890ec2cc7c1272d13e6e1b70260	70d3b890ec2cc7c1272d13e6e1b70260	la girafe	general	1	2025-12-19 22:24:08	2025-12-19 22:24:08
766	3	sport_ai_c5855096b0130d2c7558df6cc91727a0	c5855096b0130d2c7558df6cc91727a0	le hockey	sport	23	2025-12-24 02:05:52	2025-12-24 02:05:52
767	4	cuisine_ai_6f5999aa52d4b0215d0a52081b570f52	6f5999aa52d4b0215d0a52081b570f52	faux	cuisine	1	2025-12-29 23:08:50	2025-12-29 23:08:50
768	4	histoire_ai_65e1a927b9d550667fea8845e4403055	65e1a927b9d550667fea8845e4403055	1912	histoire	1	2025-12-29 23:16:53	2025-12-29 23:16:53
769	4	histoire_ai_6b73742431a72a01e233fad0f402ada3	6b73742431a72a01e233fad0f402ada3	satoshi nakamoto	histoire	1	2025-12-29 23:17:22	2025-12-29 23:17:22
770	4	histoire_ai_5d71747419ce375fa1cd9afc2ebf1e5e	5d71747419ce375fa1cd9afc2ebf1e5e	1961	histoire	1	2025-12-29 23:17:49	2025-12-29 23:17:49
771	4	general_ai_edada8f0116fccc6878d2733c24978f7	edada8f0116fccc6878d2733c24978f7	zèbre	general	1	2025-12-29 23:26:11	2025-12-29 23:26:11
772	4	general_ai_6d8ef71ebe18524b084e4b4dd73b06df	6d8ef71ebe18524b084e4b4dd73b06df	l'argent	general	1	2025-12-29 23:26:30	2025-12-29 23:26:30
773	4	general_ai_28a1ea57fd2f9e72d23bc35cdf2921b1	28a1ea57fd2f9e72d23bc35cdf2921b1	océan indien	general	1	2025-12-29 23:27:02	2025-12-29 23:27:02
774	4	general_ai_02c41fe903db32a8ac5cabbbafb9c586	02c41fe903db32a8ac5cabbbafb9c586	l'ornithorynque	general	1	2025-12-29 23:27:24	2025-12-29 23:27:24
775	4	general_ai_93a68c1a2fd7616855ab7acf81d7f2b4	93a68c1a2fd7616855ab7acf81d7f2b4	tim berners-lee	general	1	2025-12-29 23:27:43	2025-12-29 23:27:43
776	4	geographie_ai_d76f8bafdad1ad9792e614b3aa03f27c	d76f8bafdad1ad9792e614b3aa03f27c	lisbonne	geographie	1	2025-12-30 01:31:39	2025-12-30 01:31:39
777	4	geographie_ai_5534a7b70210f1fe89cf47e97d263a5a	5534a7b70210f1fe89cf47e97d263a5a	amérique du sud	geographie	1	2025-12-30 01:32:12	2025-12-30 01:32:12
778	4	geographie_ai_5e72612e5358278499e46c81616f4594	5e72612e5358278499e46c81616f4594	le danube	geographie	1	2025-12-30 01:32:39	2025-12-30 01:32:39
779	4	geographie_ai_e2511a3df40e1d3046353fddabfe11d8	e2511a3df40e1d3046353fddabfe11d8	la grande barrière de corail	geographie	1	2025-12-30 01:32:54	2025-12-30 01:32:54
780	4	geographie_ai_d4ccc7e2e2624a2d3a32d557889c9564	d4ccc7e2e2624a2d3a32d557889c9564	1881	geographie	1	2025-12-30 01:33:22	2025-12-30 01:33:22
781	4	geographie_ai_86e7908ce76c7248d5167b112aef5c72	86e7908ce76c7248d5167b112aef5c72	la seine	geographie	1	2025-12-30 01:33:44	2025-12-30 01:33:44
782	4	geographie_ai_9626e80efb7defed8cdcd37a5739983e	9626e80efb7defed8cdcd37a5739983e	chili	geographie	1	2025-12-30 01:33:59	2025-12-30 01:33:59
783	4	geographie_ai_4daf14d2ea620ba0a9f154542f457208	4daf14d2ea620ba0a9f154542f457208	russie	geographie	1	2025-12-30 01:34:21	2025-12-30 01:34:21
784	4	geographie_ai_7e10a688cc63c895b8ae0c818358ce40	7e10a688cc63c895b8ae0c818358ce40	canberra	geographie	1	2025-12-30 01:34:47	2025-12-30 01:34:47
785	4	geographie_ai_b5b34b993fc5c86a4131b74d0f4212f6	b5b34b993fc5c86a4131b74d0f4212f6	les andes	geographie	1	2025-12-30 01:35:04	2025-12-30 01:35:04
786	4	geographie_ai_17a7b316a546da725abc9eae452db554	17a7b316a546da725abc9eae452db554	l'oural	geographie	1	2025-12-30 03:00:07	2025-12-30 03:00:07
787	4	geographie_ai_fe2d47099f97017cefb066af72d09bc0	fe2d47099f97017cefb066af72d09bc0	faux	geographie	1	2025-12-30 03:00:39	2025-12-30 03:00:39
788	4	geographie_ai_10110349169ef3da90602c03c0dc290d	10110349169ef3da90602c03c0dc290d	vrai	geographie	1	2025-12-30 03:01:05	2025-12-30 03:01:05
789	4	geographie_ai_a4c997d0a90ff6cdce0e2bc69f6c9e99	a4c997d0a90ff6cdce0e2bc69f6c9e99	florence	geographie	1	2025-12-30 03:01:26	2025-12-30 03:01:26
790	4	geographie_ai_1147b5927da9f97811c34c0b99dbf8b1	1147b5927da9f97811c34c0b99dbf8b1	australie	geographie	1	2025-12-30 03:02:02	2025-12-30 03:02:02
791	4	geographie_ai_6013431cdd85668f0f8c05186d2dad55	6013431cdd85668f0f8c05186d2dad55	l'antarctique	geographie	1	2025-12-30 03:02:30	2025-12-30 03:02:30
792	4	geographie_ai_249e99561f8ffca8ee1eab043ced62b0	249e99561f8ffca8ee1eab043ced62b0	antarctique	geographie	1	2025-12-30 03:02:52	2025-12-30 03:02:52
793	4	geographie_ai_69631279479d09a9dd3a001566cfeade	69631279479d09a9dd3a001566cfeade	italie	geographie	1	2025-12-30 03:03:14	2025-12-30 03:03:14
794	4	geographie_ai_335b73ba6d4e8441b6a6307a49cbfa71	335b73ba6d4e8441b6a6307a49cbfa71	le sahara	geographie	1	2025-12-30 03:03:35	2025-12-30 03:03:35
795	4	geographie_ai_73d9cc7361b53583e52eec54c041a338	73d9cc7361b53583e52eec54c041a338	l'oural	geographie	1	2025-12-30 03:03:52	2025-12-30 03:03:52
796	8	geographie_ai_910cd1e8f082d49dd9532eaceead619b	910cd1e8f082d49dd9532eaceead619b	dans l'arctique	geographie	1	2025-12-31 21:02:17	2025-12-31 21:02:17
797	8	geographie_ai_dc52ec9eb52d47b72f96c2f2a5cb8239	dc52ec9eb52d47b72f96c2f2a5cb8239	australie	geographie	1	2025-12-31 21:02:39	2025-12-31 21:02:39
798	8	geographie_ai_f5819a9aacdc6826492a51bcb69c01ea	f5819a9aacdc6826492a51bcb69c01ea	vrai	geographie	1	2025-12-31 21:02:58	2025-12-31 21:02:58
799	8	geographie_ai_86e7908ce76c7248d5167b112aef5c72	86e7908ce76c7248d5167b112aef5c72	la seine	geographie	1	2025-12-31 21:03:24	2025-12-31 21:03:24
800	3	general_ai_1c2d31c5743da0f86ad3550de5624156	1c2d31c5743da0f86ad3550de5624156	vrai	general	8	2026-01-02 22:04:55	2026-01-02 22:04:55
801	3	general_ai_28a1ea57fd2f9e72d23bc35cdf2921b1	28a1ea57fd2f9e72d23bc35cdf2921b1	océan indien	general	8	2026-01-02 22:05:37	2026-01-02 22:05:37
802	3	general_ai_8b2569ba15f9920bacd7d1abe80333ee	8b2569ba15f9920bacd7d1abe80333ee	paris	general	8	2026-01-02 22:06:19	2026-01-02 22:06:19
803	3	general_ai_93586f36d98856db914a5f8f3dbb9319	93586f36d98856db914a5f8f3dbb9319	le cerf	general	8	2026-01-02 22:07:00	2026-01-02 22:07:00
804	3	general_ai_6fb20023ba6a253510d506a8a08a58ea	6fb20023ba6a253510d506a8a08a58ea	vrai	general	8	2026-01-02 22:07:42	2026-01-02 22:07:42
805	4	general_ai_5099c4b8cdbedf3f8032481f971f130a	5099c4b8cdbedf3f8032481f971f130a	vrai	general	1	2026-01-03 21:38:18	2026-01-03 21:38:18
806	4	general_ai_150f0c8364941a4cd00c0f8d32658270	150f0c8364941a4cd00c0f8d32658270	l'automobile à moteur à essence	general	1	2026-01-03 21:38:49	2026-01-03 21:38:49
807	4	general_ai_c58479a0327b36c54888d1c05c6ce337	c58479a0327b36c54888d1c05c6ce337	vrai	general	1	2026-01-03 21:39:11	2026-01-03 21:39:11
808	4	general_ai_c01ced34abe1edf06e073cb24f42b605	c01ced34abe1edf06e073cb24f42b605	le hockey sur glace	general	1	2026-01-03 21:39:50	2026-01-03 21:39:50
809	4	general_ai_408d3bce64abceb330fa24d6a311c806	408d3bce64abceb330fa24d6a311c806	le castor	general	1	2026-01-03 21:40:28	2026-01-03 21:40:28
810	4	general_ai_501c8cbd2f4324bc8b288df76188b683	501c8cbd2f4324bc8b288df76188b683	dioxyde de carbone	general	1	2026-01-03 21:40:49	2026-01-03 21:40:49
811	4	general_ai_150950775973993d1671ff51a92d49dd	150950775973993d1671ff51a92d49dd	le léopard	general	1	2026-01-03 21:41:10	2026-01-03 21:41:10
812	4	general_ai_697475dbb9423c99bf37c14ceef483d5	697475dbb9423c99bf37c14ceef483d5	l'oreille	general	1	2026-01-03 21:41:45	2026-01-03 21:41:45
813	4	general_ai_fc74cabaa7382af8bffdf3633733b56a	fc74cabaa7382af8bffdf3633733b56a	le patinage artistique	general	1	2026-01-03 21:42:13	2026-01-03 21:42:13
814	4	general_ai_849f9ea60c62adfe5bbd7d6cbabd87fb	849f9ea60c62adfe5bbd7d6cbabd87fb	l'éléphante	general	1	2026-01-03 21:42:37	2026-01-03 21:42:37
815	4	general_ai_9a84086555cd3f469c6f95a7bf468ca5	9a84086555cd3f469c6f95a7bf468ca5	la tortue luth	general	1	2026-01-03 21:44:22	2026-01-03 21:44:22
816	4	general_ai_fd0324ff191c6315c37ca9519609b4d0	fd0324ff191c6315c37ca9519609b4d0	vrai	general	1	2026-01-03 21:44:43	2026-01-03 21:44:43
817	4	general_ai_7623027d5e01c4c5dc5513ad93f0bb4c	7623027d5e01c4c5dc5513ad93f0bb4c	le colibri	general	1	2026-01-03 21:45:07	2026-01-03 21:45:07
818	4	general_ai_72fea253d349c2a7708da9b47c1b9d05	72fea253d349c2a7708da9b47c1b9d05	océan atlantique	general	1	2026-01-03 21:45:32	2026-01-03 21:45:32
819	4	general_ai_c741dcb24770ce05e312ef5e0a8554a6	c741dcb24770ce05e312ef5e0a8554a6	la poule	general	1	2026-01-03 21:46:08	2026-01-03 21:46:08
820	4	general_ai_9803d0a95a67a59c314b98f05a85b58d	9803d0a95a67a59c314b98f05a85b58d	faux	general	1	2026-01-03 21:46:31	2026-01-03 21:46:31
821	4	general_ai_720749b7df1798782bdfd363c1a73597	720749b7df1798782bdfd363c1a73597	la baleine bleue	general	1	2026-01-03 21:47:15	2026-01-03 21:47:15
822	4	general_ai_7bfa58695c8c33ac5a434b0dd30ff8da	7bfa58695c8c33ac5a434b0dd30ff8da	la girafe	general	1	2026-01-03 21:47:38	2026-01-03 21:47:38
823	4	general_ai_5da9513c9bc22680db84e499ef8337de	5da9513c9bc22680db84e499ef8337de	la pieuvre	general	1	2026-01-03 21:48:15	2026-01-03 21:48:15
824	3	general_ai_0b28527f49eeb9c9f2af7ce355222cf7	0b28527f49eeb9c9f2af7ce355222cf7	l'éléphant	general	5	2026-01-05 17:35:47	2026-01-05 17:35:47
825	3	general_ai_dd302aa2cfee00034cb3255f9caee762	dd302aa2cfee00034cb3255f9caee762	léonard de vinci	general	5	2026-01-05 17:36:28	2026-01-05 17:36:28
826	3	general_ai_361bc7df09c406b7d1989eba6c9af9ac	361bc7df09c406b7d1989eba6c9af9ac	pink floyd	general	5	2026-01-05 17:37:01	2026-01-05 17:37:01
827	3	general_ai_5820f8450f0355fa2de95dfa00c362dc	5820f8450f0355fa2de95dfa00c362dc	canberra	general	5	2026-01-05 17:37:37	2026-01-05 17:37:37
828	3	general_ai_834fd7f138bd0743d06edfca26bdfcc9	834fd7f138bd0743d06edfca26bdfcc9	mercure	general	5	2026-01-05 17:38:05	2026-01-05 17:38:05
829	3	general_ai_5ed74fd287571f8f769cc27a41d7e7a2	5ed74fd287571f8f769cc27a41d7e7a2	les États-unis	general	5	2026-01-05 17:38:44	2026-01-05 17:38:44
830	3	general_ai_44e1f84cb7e885096a9dbe65281694d8	44e1f84cb7e885096a9dbe65281694d8	le bronze	general	5	2026-01-05 17:39:12	2026-01-05 17:39:12
831	3	general_ai_bff1a9841f5dff58a700147022a650e4	bff1a9841f5dff58a700147022a650e4	faux	general	5	2026-01-05 18:43:10	2026-01-05 18:43:10
832	3	general_ai_bedbfb6ed10a2a9c4d0217ab2e72f1ee	bedbfb6ed10a2a9c4d0217ab2e72f1ee	vrai	general	5	2026-01-05 18:44:06	2026-01-05 18:44:06
833	3	general_ai_77243b067d1fb6a83d44a5da3028d94a	77243b067d1fb6a83d44a5da3028d94a	faux	general	5	2026-01-05 18:44:45	2026-01-05 18:44:45
834	3	general_ai_dcb2108d8804c0156a7a660be9381d32	dcb2108d8804c0156a7a660be9381d32	le castor	general	5	2026-01-05 18:45:26	2026-01-05 18:45:26
835	3	general_ai_91310a513f0e133dac196470c62eb6c9	91310a513f0e133dac196470c62eb6c9	l'ours noir	general	5	2026-01-05 18:46:07	2026-01-05 18:46:07
836	3	general_ai_db1b77ef01e3da6a06a15d6eb3741ec6	db1b77ef01e3da6a06a15d6eb3741ec6	l'hydrogène	general	5	2026-01-05 18:46:48	2026-01-05 18:46:48
837	3	general_ai_449b09a5dbfbfaf7a845080e878ab542	449b09a5dbfbfaf7a845080e878ab542	la russie	general	5	2026-01-05 18:47:29	2026-01-05 18:47:29
838	3	general_ai_c6eabecaf633f30c826e49624c5fbf69	c6eabecaf633f30c826e49624c5fbf69	new york	general	5	2026-01-05 18:48:09	2026-01-05 18:48:09
839	3	general_ai_3aeb2ad490a4eff979aeeec4c7182b64	3aeb2ad490a4eff979aeeec4c7182b64	le caméléon	general	12	2026-01-06 13:43:32	2026-01-06 13:43:32
840	3	general_ai_7e10a688cc63c895b8ae0c818358ce40	7e10a688cc63c895b8ae0c818358ce40	canberra	general	12	2026-01-06 13:44:12	2026-01-06 13:44:12
841	3	general_ai_64788152b50ad46ae8b5f9e92d79761a	64788152b50ad46ae8b5f9e92d79761a	londres	general	12	2026-01-06 13:44:37	2026-01-06 13:44:37
842	3	general_ai_64a6996c2e7c4a2a224fd0ab22da127e	64a6996c2e7c4a2a224fd0ab22da127e	komodo	general	12	2026-01-06 13:55:21	2026-01-06 13:55:21
843	3	general_ai_3e6e42364a8d2a4f10b54aa5103eeced	3e6e42364a8d2a4f10b54aa5103eeced	le koala	general	12	2026-01-06 13:55:51	2026-01-06 13:55:51
844	3	general_ai_98b72c7dec3a3315c293150f4daa2f3b	98b72c7dec3a3315c293150f4daa2f3b	marc chagall	general	12	2026-01-06 13:56:31	2026-01-06 13:56:31
845	3	general_ai_6a418201f7e59d12eeada48c2500a531	6a418201f7e59d12eeada48c2500a531	l'albatros hurleur	general	12	2026-01-06 13:57:12	2026-01-06 13:57:12
846	3	general_ai_f7ab8bbba3aec97967a3ecc502d14037	f7ab8bbba3aec97967a3ecc502d14037	la seine	general	12	2026-01-06 13:57:52	2026-01-06 13:57:52
847	3	general_ai_2bd59889a8ac6f17c125286b725e2750	2bd59889a8ac6f17c125286b725e2750	le tibre	general	12	2026-01-06 13:58:33	2026-01-06 13:58:33
848	3	general_ai_6bf598d3f2b149e74c9032ce92924eb0	6bf598d3f2b149e74c9032ce92924eb0	1963	general	12	2026-01-06 13:59:13	2026-01-06 13:59:13
849	3	general_ai_5fa5225997a982a89f3259459b979286	5fa5225997a982a89f3259459b979286	le koala	general	12	2026-01-06 13:59:53	2026-01-06 13:59:53
850	3	general_ai_8da2a31e451fd93d079c14bf45ee1a36	8da2a31e451fd93d079c14bf45ee1a36	la chaîne de montage	general	12	2026-01-06 14:00:33	2026-01-06 14:00:33
851	3	general_ai_ddff089c5e7a4f84f746f944f4491a63	ddff089c5e7a4f84f746f944f4491a63	le phosphore	general	12	2026-01-06 14:01:13	2026-01-06 14:01:13
852	3	general_ai_764f6794942bb3cf01c1e1902c1ad0bf	764f6794942bb3cf01c1e1902c1ad0bf	la loutre de mer	general	6	2026-01-06 21:07:18	2026-01-06 21:07:18
853	3	general_ai_75ca45e24b7f7c764c0ab04a832df1d3	75ca45e24b7f7c764c0ab04a832df1d3	vrai	general	6	2026-01-06 21:07:58	2026-01-06 21:07:58
854	3	general_ai_7a277770f8488587ff7c7be1ba84a7c6	7a277770f8488587ff7c7be1ba84a7c6	le lièvre	general	8	2026-01-17 10:44:38	2026-01-17 10:44:38
855	3	general_ai_8b2cf1aa9e598fa82b343f2fb90b666a	8b2cf1aa9e598fa82b343f2fb90b666a	l'azote	general	8	2026-01-17 11:30:36	2026-01-17 11:30:36
856	3	general_ai_b06dc6e591f92b9ae9b5b38da5fd4ade	b06dc6e591f92b9ae9b5b38da5fd4ade	l'inde	general	8	2026-01-17 11:31:16	2026-01-17 11:31:16
857	3	general_ai_57c700066d0dc99e6c94d20b732c0c7d	57c700066d0dc99e6c94d20b732c0c7d	l'autriche	general	8	2026-01-17 11:31:55	2026-01-17 11:31:55
858	3	general_ai_65b5be5bf5ca7356a3d8a6f3a6dfd04d	65b5be5bf5ca7356a3d8a6f3a6dfd04d	bleu	general	8	2026-01-17 11:32:35	2026-01-17 11:32:35
859	3	general_ai_b624741e8e4ccc831f0591aeae8518a5	b624741e8e4ccc831f0591aeae8518a5	l'ours noir	general	8	2026-01-17 11:33:14	2026-01-17 11:33:14
860	3	general_ai_b459e4e364bbb5a5a47586409a9bc42e	b459e4e364bbb5a5a47586409a9bc42e	la chauve-souris	general	8	2026-01-17 11:33:42	2026-01-17 11:33:42
861	3	general_ai_14657a80403e17f52ee746d67b940f49	14657a80403e17f52ee746d67b940f49	océan atlantique	general	8	2026-01-17 11:38:52	2026-01-17 11:38:52
862	3	general_ai_0dc549a83df8ae0241f2728e4ae4ff4f	0dc549a83df8ae0241f2728e4ae4ff4f	le dauphin	general	8	2026-01-17 12:28:36	2026-01-17 12:28:36
863	3	general_ai_00ac98124c23cfbccd8499b005bd5397	00ac98124c23cfbccd8499b005bd5397	la tortue de mer	general	8	2026-01-18 02:21:08	2026-01-18 02:21:08
864	3	general_ai_61376eb250e85e0cdeba9f7474fc92c2	61376eb250e85e0cdeba9f7474fc92c2	le russe	general	8	2026-01-18 02:43:15	2026-01-18 02:43:15
865	3	general_ai_a16115b36aa1dc6cd5a5aa579a94bd9d	a16115b36aa1dc6cd5a5aa579a94bd9d	l'okapi	general	8	2026-01-18 02:43:47	2026-01-18 02:43:47
866	3	general_ai_6258a09308ce827c837b9935a5504f10	6258a09308ce827c837b9935a5504f10	faux	general	8	2026-01-18 02:44:13	2026-01-18 02:44:13
867	3	general_ai_aa64d0a56949ec09a18ac74326dd7f9d	aa64d0a56949ec09a18ac74326dd7f9d	l'orange	general	8	2026-01-18 02:44:43	2026-01-18 02:44:43
868	3	general_ai_cac7518a4d1207705c364160686c676c	cac7518a4d1207705c364160686c676c	l'axolotl	general	8	2026-01-18 02:45:23	2026-01-18 02:45:23
869	3	general_ai_23473e538f7223fd00a44cd311383321	23473e538f7223fd00a44cd311383321	océan atlantique	general	8	2026-01-18 02:45:58	2026-01-18 02:45:58
870	3	general_ai_cc3b99301614e8b5db4fbf8cb4c04ba9	cc3b99301614e8b5db4fbf8cb4c04ba9	l'aigle à tête blanche	general	8	2026-01-18 02:46:35	2026-01-18 02:46:35
871	3	general_ai_dbf6c32a3729380ad4ed045220bd6cd4	dbf6c32a3729380ad4ed045220bd6cd4	rouge et blanc	general	8	2026-01-18 02:47:13	2026-01-18 02:47:13
872	3	general_ai_2b76843bdd74619bb97486f77346f8cc	2b76843bdd74619bb97486f77346f8cc	euro	general	8	2026-01-18 02:47:49	2026-01-18 02:47:49
873	3	general_ai_98bbd71b9befb196385074ac8bb58980	98bbd71b9befb196385074ac8bb58980	bill gates	general	8	2026-01-18 02:48:28	2026-01-18 02:48:28
874	3	general_ai_f29117d88b34d2b25a562cc102204f2e	f29117d88b34d2b25a562cc102204f2e	le violet	general	8	2026-01-18 02:52:40	2026-01-18 02:52:40
875	3	general_ai_fddb82ec66ced832b47a0f8b4512137f	fddb82ec66ced832b47a0f8b4512137f	rougeâtre	general	8	2026-01-18 02:53:15	2026-01-18 02:53:15
876	3	general_ai_36c884a4f1ebda6b44f5a7d2ca59f8d5	36c884a4f1ebda6b44f5a7d2ca59f8d5	louis pasteur	general	8	2026-01-18 02:53:50	2026-01-18 02:53:50
877	3	general_ai_5da9513c9bc22680db84e499ef8337de	5da9513c9bc22680db84e499ef8337de	la pieuvre	general	8	2026-01-18 02:54:28	2026-01-18 02:54:28
878	3	general_ai_88ddfe6a15c28303f3efa991ad28cb1d	88ddfe6a15c28303f3efa991ad28cb1d	wolfgang amadeus mozart	general	8	2026-01-18 02:55:01	2026-01-18 02:55:01
879	3	general_ai_10315da255db717e2185ab45fd54c4d2	10315da255db717e2185ab45fd54c4d2	l'ornithorynque	general	12	2026-01-18 12:38:52	2026-01-18 12:38:52
880	3	general_ai_236d50ca7811d1c3bef65ac5aba1a02a	236d50ca7811d1c3bef65ac5aba1a02a	paris	general	12	2026-01-18 12:39:13	2026-01-18 12:39:13
881	3	general_ai_edb9179ba1547689cbac7142e5b244f4	edb9179ba1547689cbac7142e5b244f4	anglais	general	12	2026-01-18 12:39:38	2026-01-18 12:39:38
882	3	general_ai_0ee3eacf4329be25780db7499454b0e9	0ee3eacf4329be25780db7499454b0e9	la lune	general	12	2026-01-18 12:40:08	2026-01-18 12:40:08
883	3	general_ai_d16324b2dfe38547664d75af17e89186	d16324b2dfe38547664d75af17e89186	or	general	12	2026-01-18 12:40:30	2026-01-18 12:40:30
884	3	general_ai_7b8d8bfb8b0ebd3df31ec5f763a264e2	7b8d8bfb8b0ebd3df31ec5f763a264e2	berlin	general	12	2026-01-18 12:40:48	2026-01-18 12:40:48
885	3	general_ai_a380f0325ceb827326116c3d7b33b571	a380f0325ceb827326116c3d7b33b571	faux	general	12	2026-01-18 12:41:23	2026-01-18 12:41:23
886	3	general_ai_cfaf0a0fc23f4874558c2fac26e129fc	cfaf0a0fc23f4874558c2fac26e129fc	vrai	general	12	2026-01-18 12:41:52	2026-01-18 12:41:52
887	3	general_ai_f10b2e55c48f08a4e830608fe4e1af7d	f10b2e55c48f08a4e830608fe4e1af7d	l'azote	general	12	2026-01-18 12:42:17	2026-01-18 12:42:17
888	3	general_ai_51dc2a1edb3501f484ca1730100d44c5	51dc2a1edb3501f484ca1730100d44c5	le volt	general	12	2026-01-18 12:42:38	2026-01-18 12:42:38
889	3	general_ai_c009a8dd8924e5f6bc3bc48579a820b9	c009a8dd8924e5f6bc3bc48579a820b9	les pays-bas	general	12	2026-01-18 12:44:11	2026-01-18 12:44:11
890	3	general_ai_91341be8ba9a750a31346a97d2455b4c	91341be8ba9a750a31346a97d2455b4c	le yangtsé	general	12	2026-01-18 12:44:43	2026-01-18 12:44:43
891	3	general_ai_86e7908ce76c7248d5167b112aef5c72	86e7908ce76c7248d5167b112aef5c72	la seine	general	12	2026-01-18 12:45:05	2026-01-18 12:45:05
892	3	general_ai_045076c90b74976a96e8a942dea3ef40	045076c90b74976a96e8a942dea3ef40	le danemark	general	12	2026-01-18 12:45:30	2026-01-18 12:45:30
893	3	general_ai_d2cb295752fcd19bbb91de559376d30c	d2cb295752fcd19bbb91de559376d30c	jean sans terre	general	12	2026-01-18 12:46:00	2026-01-18 12:46:00
894	3	general_ai_e69e0ca1ef6da40b935a27a3cfce50aa	e69e0ca1ef6da40b935a27a3cfce50aa	le criquet	general	12	2026-01-18 12:46:32	2026-01-18 12:46:32
895	3	general_ai_98274b6d1f290a62c46f9859e1374e30	98274b6d1f290a62c46f9859e1374e30	l'anglais	general	12	2026-01-18 12:47:02	2026-01-18 12:47:02
896	3	general_ai_6507cf888be846670e7f7323662f9ea3	6507cf888be846670e7f7323662f9ea3	l'argent	general	12	2026-01-18 12:47:27	2026-01-18 12:47:27
897	3	general_ai_e33d7c648e7400e6caf15148d9eed104	e33d7c648e7400e6caf15148d9eed104	les États-unis	general	12	2026-01-18 12:48:00	2026-01-18 12:48:00
898	3	general_ai_fae3b36219677b42305de3bf70587dfd	fae3b36219677b42305de3bf70587dfd	maranello	general	12	2026-01-18 12:48:22	2026-01-18 12:48:22
899	3	general_ai_ebb71c2ba070d71468219e5f15446524	ebb71c2ba070d71468219e5f15446524	le portugal	general	12	2026-01-18 13:05:14	2026-01-18 13:05:14
900	3	general_ai_9abf2405f8242708d373703748cfe2ab	9abf2405f8242708d373703748cfe2ab	l'italie	general	12	2026-01-18 13:05:44	2026-01-18 13:05:44
901	3	general_ai_c1dc782ac10951b25c0cb8deba8854c2	c1dc782ac10951b25c0cb8deba8854c2	au japon	general	12	2026-01-18 13:06:08	2026-01-18 13:06:08
902	3	general_ai_89ed19dd052b819ec9160f9d1fabd281	89ed19dd052b819ec9160f9d1fabd281	la finlande	general	12	2026-01-18 13:06:37	2026-01-18 13:06:37
903	3	general_ai_a3c08e687b39cfd1ee6e4a2ce72e7d46	a3c08e687b39cfd1ee6e4a2ce72e7d46	le danemark	general	12	2026-01-18 13:07:08	2026-01-18 13:07:08
904	3	general_ai_3bd565072dbfd20ba8bfb7c959bdc2ba	3bd565072dbfd20ba8bfb7c959bdc2ba	l'eau	general	12	2026-01-18 13:07:42	2026-01-18 13:07:42
905	3	general_ai_ff9b69f4208aa6a7910c28c687e0e432	ff9b69f4208aa6a7910c28c687e0e432	le cachalot	general	12	2026-01-18 13:08:17	2026-01-18 13:08:17
906	3	general_ai_0ed45294be72f686bb6bdfb2fe2cf86a	0ed45294be72f686bb6bdfb2fe2cf86a	atlanta	general	12	2026-01-18 13:08:46	2026-01-18 13:08:46
907	3	general_ai_91828cae42cfa0f83d07276f4793de86	91828cae42cfa0f83d07276f4793de86	l'aluminium	general	12	2026-01-18 13:09:08	2026-01-18 13:09:08
908	3	general_ai_3c40538633271674eefccf70c5a5d22b	3c40538633271674eefccf70c5a5d22b	l'oryx	general	12	2026-01-18 13:09:45	2026-01-18 13:09:45
909	3	general_ai_6dae452e1dabef002f0b866a9911c69a	6dae452e1dabef002f0b866a9911c69a	390 km/h	general	12	2026-01-18 13:10:30	2026-01-18 13:10:30
910	3	general_ai_aeffdaa18d2a3b0010a7894c969efe04	aeffdaa18d2a3b0010a7894c969efe04	1976	general	12	2026-01-18 13:10:57	2026-01-18 13:10:57
911	3	general_ai_eda90a7c51e8bf2e6c18a7fc55226a17	eda90a7c51e8bf2e6c18a7fc55226a17	le salto angel	general	12	2026-01-18 13:11:22	2026-01-18 13:11:22
912	3	general_ai_58e22a1a60d25d60f3a52a243f1e2dae	58e22a1a60d25d60f3a52a243f1e2dae	le tibre	general	12	2026-01-18 13:11:48	2026-01-18 13:11:48
913	3	general_ai_a51443b0c1ffdba4d432a1d7c66d297d	a51443b0c1ffdba4d432a1d7c66d297d	méditerranée	general	12	2026-01-18 13:12:16	2026-01-18 13:12:16
914	3	general_ai_c280c37f51337897371f6f06c2b4a08c	c280c37f51337897371f6f06c2b4a08c	la suède	general	12	2026-01-18 13:12:38	2026-01-18 13:12:38
915	3	general_ai_2ee97aa2a4879eb6ddf5f8f21fb76402	2ee97aa2a4879eb6ddf5f8f21fb76402	séoul	general	12	2026-01-18 13:13:11	2026-01-18 13:13:11
916	3	general_ai_69c04773a3e790177a04314e32f22642	69c04773a3e790177a04314e32f22642	le danube	general	12	2026-01-18 13:13:33	2026-01-18 13:13:33
917	3	general_ai_e97011a364a74221d9ffbd235db8228d	e97011a364a74221d9ffbd235db8228d	l'or	general	12	2026-01-18 13:14:11	2026-01-18 13:14:11
918	3	general_ai_eb32fc1b01901f9cc4a5966f4b0718d6	eb32fc1b01901f9cc4a5966f4b0718d6	l'indonésie	general	12	2026-01-18 13:14:42	2026-01-18 13:14:42
919	3	general_ai_ff45ea1c398dc001aa55f8400c728a2c	ff45ea1c398dc001aa55f8400c728a2c	un serpent	general	12	2026-01-18 23:03:03	2026-01-18 23:03:03
920	3	general_ai_9d1727ca5714d51b27493bf2f76b590b	9d1727ca5714d51b27493bf2f76b590b	la ouate de cellulose	general	12	2026-01-18 23:03:42	2026-01-18 23:03:42
921	3	general_ai_8fa15ca93d780a0cda6b99a866b375dd	8fa15ca93d780a0cda6b99a866b375dd	moscou	general	12	2026-01-18 23:04:21	2026-01-18 23:04:21
922	3	general_ai_cd913e3d923ef6af71690899be3739d2	cd913e3d923ef6af71690899be3739d2	vrai	general	12	2026-01-18 23:05:00	2026-01-18 23:05:00
923	3	general_ai_eda988de5abb384e3efc427f3f331d93	eda988de5abb384e3efc427f3f331d93	l'imprimerie à caractères mobiles	general	12	2026-01-18 23:05:38	2026-01-18 23:05:38
924	3	general_ai_074165a5c64fe0856d228b1956d2b7e5	074165a5c64fe0856d228b1956d2b7e5	le caméléon	general	12	2026-01-18 23:06:17	2026-01-18 23:06:17
925	3	general_ai_9e7a4c7ba5f1adbc881947f8a3aa17c0	9e7a4c7ba5f1adbc881947f8a3aa17c0	faux	general	12	2026-01-18 23:06:56	2026-01-18 23:06:56
926	3	general_ai_82e0e3157e44fa39c7a66130a0c24daa	82e0e3157e44fa39c7a66130a0c24daa	la cigogne	general	12	2026-01-18 23:07:35	2026-01-18 23:07:35
927	3	general_ai_0a6e70fd1fb70864ab7bb5b14008a4dc	0a6e70fd1fb70864ab7bb5b14008a4dc	nicolas copernic	general	12	2026-01-18 23:08:14	2026-01-18 23:08:14
928	3	general_ai_294384e30819baf63fbb4f350dad0b15	294384e30819baf63fbb4f350dad0b15	le sable siliceux	general	12	2026-01-18 23:08:39	2026-01-18 23:08:39
929	3	general_ai_c43c1725cc87faafbea01eba6c5f6eca	c43c1725cc87faafbea01eba6c5f6eca	l'anglais	general	12	2026-01-18 23:16:59	2026-01-18 23:16:59
930	3	general_ai_ceb443350642fd332e64e807a3b09f63	ceb443350642fd332e64e807a3b09f63	les riourikides	general	12	2026-01-18 23:17:24	2026-01-18 23:17:24
931	3	general_ai_1bf2d77a0962841f561cd0c4c1ec45b5	1bf2d77a0962841f561cd0c4c1ec45b5	lyon	general	12	2026-01-18 23:18:08	2026-01-18 23:18:08
932	3	general_ai_2349abe6c7d45f540c4689fb1c9add71	2349abe6c7d45f540c4689fb1c9add71	kyoto	general	12	2026-01-18 23:18:44	2026-01-18 23:18:44
933	3	general_ai_8a67636713f86df68260b865f4995604	8a67636713f86df68260b865f4995604	le costa rica	general	12	2026-01-18 23:19:26	2026-01-18 23:19:26
934	3	general_ai_30799e28308f2966ce5a019a92d1e320	30799e28308f2966ce5a019a92d1e320	l'azote	general	12	2026-01-18 23:20:04	2026-01-18 23:20:04
935	3	general_ai_f68cb150dab69d252c9e62c2d16a90ec	f68cb150dab69d252c9e62c2d16a90ec	le sahara	general	12	2026-01-18 23:20:43	2026-01-18 23:20:43
936	3	general_ai_65cd371a6d1b72ba1b1b063dc332d709	65cd371a6d1b72ba1b1b063dc332d709	faux	general	12	2026-01-18 23:21:22	2026-01-18 23:21:22
937	3	general_ai_127cb6b324092ee1527d78945f7a7ac9	127cb6b324092ee1527d78945f7a7ac9	arlington	general	12	2026-01-18 23:22:00	2026-01-18 23:22:00
938	3	general_ai_ac31f1263cc631a3077c687b2a068f4f	ac31f1263cc631a3077c687b2a068f4f	l'inde	general	12	2026-01-18 23:22:39	2026-01-18 23:22:39
939	3	general_ai_283022dce377b48013cea72737b89007	283022dce377b48013cea72737b89007	le gorille	general	12	2026-01-18 23:23:19	2026-01-18 23:23:19
940	3	general_ai_c83b3d3ac5dea2d9491523f1ed173ef3	c83b3d3ac5dea2d9491523f1ed173ef3	la seine	general	12	2026-01-19 21:00:02	2026-01-19 21:00:02
941	3	general_ai_5460ad228a4541065ec809a15a0716e7	5460ad228a4541065ec809a15a0716e7	constantin ier	general	12	2026-01-19 21:00:41	2026-01-19 21:00:41
942	3	general_ai_822ff959f538a00c060ab2bd45d583e3	822ff959f538a00c060ab2bd45d583e3	le sulfate de calcium	general	12	2026-01-19 21:01:10	2026-01-19 21:01:10
943	3	general_ai_f47fe569cbc8545d6467e2dcfa1f6de9	f47fe569cbc8545d6467e2dcfa1f6de9	le nitrate d'argent	general	12	2026-01-19 21:01:37	2026-01-19 21:01:37
944	3	general_ai_49624ad1b3ce0d9ce3898ee4f52af096	49624ad1b3ce0d9ce3898ee4f52af096	l'arno	general	12	2026-01-19 21:02:16	2026-01-19 21:02:16
945	3	general_ai_52011f7f3c4336d945087a6bb1302654	52011f7f3c4336d945087a6bb1302654	le lac de garde	general	12	2026-01-19 21:12:27	2026-01-19 21:12:27
946	3	general_ai_5bc6bc156583fa2084c9c181eddf78de	5bc6bc156583fa2084c9c181eddf78de	le lac Érié	general	12	2026-01-19 21:12:59	2026-01-19 21:12:59
947	3	general_ai_6fe8d8a97032a471bbbf19a07cb2cb25	6fe8d8a97032a471bbbf19a07cb2cb25	le sable	general	12	2026-01-19 21:13:41	2026-01-19 21:13:41
948	3	general_ai_256fc600c9a9f75ddf6b3558de8ecde9	256fc600c9a9f75ddf6b3558de8ecde9	le dauphin	general	12	2026-01-19 21:14:04	2026-01-19 21:14:04
949	3	general_ai_6df943af70b4195bfe3b2c2359dbfc6a	6df943af70b4195bfe3b2c2359dbfc6a	le zèbre	general	12	2026-01-19 21:14:38	2026-01-19 21:14:38
950	3	general_ai_3598d08a283f50c4883c57ad13d0794f	3598d08a283f50c4883c57ad13d0794f	le néon	general	12	2026-01-19 21:15:28	2026-01-19 21:15:28
951	3	general_ai_5677e356b52acceb57054efd5f3edbad	5677e356b52acceb57054efd5f3edbad	le ladoga	general	12	2026-01-19 21:46:14	2026-01-19 21:46:14
952	3	general_ai_1cc21309a49c2ed67210ab91170c7b5e	1cc21309a49c2ed67210ab91170c7b5e	netscape navigator	general	12	2026-01-19 21:46:53	2026-01-19 21:46:53
953	3	general_ai_830a856cefe6b57031f6d9634b65bf7f	830a856cefe6b57031f6d9634b65bf7f	les grandes plaines	general	12	2026-01-19 21:47:30	2026-01-19 21:47:30
954	3	general_ai_382da9b0716777287c876998bf2d22c4	382da9b0716777287c876998bf2d22c4	l'italie	general	12	2026-01-19 21:48:09	2026-01-19 21:48:09
955	3	general_ai_3a2998df300201ad11b4c99d68da3e74	3a2998df300201ad11b4c99d68da3e74	la rose rouge	general	12	2026-01-19 21:48:47	2026-01-19 21:48:47
956	3	general_ai_25c99b436a10b2dd2b28e7ec507ff803	25c99b436a10b2dd2b28e7ec507ff803	le caméléon	general	12	2026-01-19 21:49:26	2026-01-19 21:49:26
957	3	general_ai_f85c1f81ee83c901b71731033295732a	f85c1f81ee83c901b71731033295732a	henri matisse	general	12	2026-01-19 21:50:05	2026-01-19 21:50:05
958	3	general_ai_8de8a2ca0ed8c0a4a8ca56af241f3a64	8de8a2ca0ed8c0a4a8ca56af241f3a64	la chauve-souris	general	12	2026-01-19 21:50:44	2026-01-19 21:50:44
959	3	general_ai_f97e135ef72495c7f31863754dec772f	f97e135ef72495c7f31863754dec772f	le pangolin	general	12	2026-01-19 21:53:36	2026-01-19 21:53:36
960	3	general_ai_e369ac24b5439d79413d36618b2f6d30	e369ac24b5439d79413d36618b2f6d30	le cuivre	general	12	2026-01-19 21:54:14	2026-01-19 21:54:14
961	3	general_ai_510bd94d5e044ea3cb0049af6941c509	510bd94d5e044ea3cb0049af6941c509	java	general	12	2026-01-19 21:54:53	2026-01-19 21:54:53
962	3	general_ai_efb8f969d9bc9df6f30b685915893ec9	efb8f969d9bc9df6f30b685915893ec9	faux	general	12	2026-01-19 21:55:36	2026-01-19 21:55:36
963	3	general_ai_eb7d9eb11d699b77dc75199cf2b3923f	eb7d9eb11d699b77dc75199cf2b3923f	vrai	general	12	2026-01-19 21:56:15	2026-01-19 21:56:15
964	3	general_ai_bf127b0c1ebf53e549eb917cb5f8f60f	bf127b0c1ebf53e549eb917cb5f8f60f	le cuivre	general	12	2026-01-19 21:56:54	2026-01-19 21:56:54
965	3	general_ai_962228173cd4352d13c11d560e1935f2	962228173cd4352d13c11d560e1935f2	le rat kangourou	general	12	2026-01-19 21:57:33	2026-01-19 21:57:33
966	3	general_ai_d7fbf28aaa9aaa1f925615ed501875e2	d7fbf28aaa9aaa1f925615ed501875e2	l'éléphante	general	12	2026-01-19 21:59:18	2026-01-19 21:59:18
967	3	general_ai_29c37d2f28229abb27050e14d0d0b0be	29c37d2f28229abb27050e14d0d0b0be	l'axolotl	general	12	2026-01-19 21:59:44	2026-01-19 21:59:44
968	3	general_ai_8016a14a41b070542ef41203781221e1	8016a14a41b070542ef41203781221e1	l'italie	general	12	2026-01-19 22:00:14	2026-01-19 22:00:14
969	3	general_ai_29423f82c5993b76f85eedf705d3fbcb	29423f82c5993b76f85eedf705d3fbcb	l'empereur auguste	general	12	2026-01-19 22:00:46	2026-01-19 22:00:46
970	3	general_ai_f4a6fe85f70f236578e71478df42e87e	f4a6fe85f70f236578e71478df42e87e	la belgique	general	12	2026-01-19 22:01:19	2026-01-19 22:01:19
971	3	general_ai_931c94eb06d0e8d1fc18334579f0c58f	931c94eb06d0e8d1fc18334579f0c58f	la panthère nébuleuse	general	12	2026-01-19 22:01:50	2026-01-19 22:01:50
972	3	general_ai_02f79a06623934eb821bfea67f63a88b	02f79a06623934eb821bfea67f63a88b	cuba	general	12	2026-01-19 22:02:26	2026-01-19 22:02:26
973	3	general_ai_4ba57f179d8553ae19da7f97ff556409	4ba57f179d8553ae19da7f97ff556409	komodo	general	12	2026-01-19 22:05:56	2026-01-19 22:05:56
974	3	general_ai_813948ad25dee603e665a638eac17761	813948ad25dee603e665a638eac17761	titan	general	12	2026-01-19 22:06:26	2026-01-19 22:06:26
975	3	general_ai_0fff5f973ec91a36c7fddcba18032181	0fff5f973ec91a36c7fddcba18032181	l'ours polaire	general	12	2026-01-19 22:06:53	2026-01-19 22:06:53
976	3	general_ai_40a53bbfc0d6da9a6a057a19e9ceee65	40a53bbfc0d6da9a6a057a19e9ceee65	le vésuve	general	12	2026-01-19 22:07:24	2026-01-19 22:07:24
977	3	general_ai_4fb735b7d3a340eca9b884f82dadaf18	4fb735b7d3a340eca9b884f82dadaf18	le cachalot	general	12	2026-01-19 22:07:55	2026-01-19 22:07:55
978	3	general_ai_59d9b32d4a255edd9caa19bc68b622b3	59d9b32d4a255edd9caa19bc68b622b3	le lac titicaca	general	12	2026-01-19 22:14:58	2026-01-19 22:14:58
979	3	general_ai_bcd35409d41963193048bc71ffb3f1ad	bcd35409d41963193048bc71ffb3f1ad	l'ocelot	general	12	2026-01-19 22:15:33	2026-01-19 22:15:33
980	3	general_ai_88a12ffb76793e92c7cb61a101252762	88a12ffb76793e92c7cb61a101252762	français	general	12	2026-01-19 22:16:09	2026-01-19 22:16:09
981	3	general_ai_d680d96ff77ba3dce38091cfa97d046e	d680d96ff77ba3dce38091cfa97d046e	le caméléon	general	12	2026-01-19 22:16:43	2026-01-19 22:16:43
982	3	general_ai_efd22d64e4d0aa392e598b485c7e0bd4	efd22d64e4d0aa392e598b485c7e0bd4	le franc suisse	general	12	2026-01-19 22:17:06	2026-01-19 22:17:06
983	3	general_ai_75383d6506b083d3ed47f5a8d1c159e2	75383d6506b083d3ed47f5a8d1c159e2	grenoble	general	12	2026-01-19 22:17:32	2026-01-19 22:17:32
984	3	general_ai_d5807cbdc22d3b6ca7c3a736dc9b32d0	d5807cbdc22d3b6ca7c3a736dc9b32d0	paris	general	12	2026-01-19 22:18:00	2026-01-19 22:18:00
985	3	general_ai_f47e537c37679a4cc4579d867735bc28	f47e537c37679a4cc4579d867735bc28	le dauphin	general	12	2026-01-19 22:18:20	2026-01-19 22:18:20
986	3	general_ai_95ec7864bb882eb4e6a5391c4c3fd848	95ec7864bb882eb4e6a5391c4c3fd848	le renard	general	12	2026-01-19 22:18:42	2026-01-19 22:18:42
987	3	general_ai_280078be6937a1d4b998c037438fa1d4	280078be6937a1d4b998c037438fa1d4	l'abeille	general	12	2026-01-19 22:19:16	2026-01-19 22:19:16
988	3	general_ai_6db8875ad38b1f703f989ef70764fade	6db8875ad38b1f703f989ef70764fade	ses écailles	general	18	2026-01-22 11:05:46	2026-01-22 11:05:46
989	3	general_ai_dd3a337dc294196542996ba7c722f609	dd3a337dc294196542996ba7c722f609	faux	general	18	2026-01-22 11:06:32	2026-01-22 11:06:32
990	3	sciences_ai_66d431a3c7176ec715dacb554903c272	66d431a3c7176ec715dacb554903c272	faux	sciences	10	2026-01-24 02:33:41	2026-01-24 02:33:41
991	3	sciences_ai_eb154a18f3d33fcad97b3f39a06b8a07	eb154a18f3d33fcad97b3f39a06b8a07	l'ohm	sciences	10	2026-01-24 02:34:20	2026-01-24 02:34:20
992	3	sciences_ai_f20742de76c61643e3fe36857370db25	f20742de76c61643e3fe36857370db25	photosynthèse	sciences	10	2026-01-24 02:34:53	2026-01-24 02:34:53
993	3	sciences_ai_1a72035cbae63d4dfbdd67eaf6451636	1a72035cbae63d4dfbdd67eaf6451636	récepteurs de glutamate	sciences	10	2026-01-24 02:44:04	2026-01-24 02:44:04
994	3	sciences_ai_e045196def77a6a2f756678e30e0abfe	e045196def77a6a2f756678e30e0abfe	le dioxyde de carbone	sciences	10	2026-01-24 02:44:43	2026-01-24 02:44:43
995	3	sciences_ai_7f333fbce6f3e175539f253004fbd527	7f333fbce6f3e175539f253004fbd527	dioxyde de carbone	sciences	10	2026-01-24 02:45:22	2026-01-24 02:45:22
996	3	sciences_ai_8eeafbcd1b518a82a5e7c299888dc28f	8eeafbcd1b518a82a5e7c299888dc28f	la photosynthèse	sciences	10	2026-01-24 02:45:59	2026-01-24 02:45:59
997	3	sciences_ai_107e54f2c8abd746152857614845ad56	107e54f2c8abd746152857614845ad56	faux	sciences	10	2026-01-24 02:46:28	2026-01-24 02:46:28
998	3	sciences_ai_2f456728e95f9e65e16a2ebdc7dedcb7	2f456728e95f9e65e16a2ebdc7dedcb7	soutenir et protéger les neurones	sciences	10	2026-01-24 02:47:01	2026-01-24 02:47:01
999	3	sciences_ai_0127d93abeafb6ad91df25dd7b9619e7	0127d93abeafb6ad91df25dd7b9619e7	sodium	sciences	10	2026-01-24 02:47:33	2026-01-24 02:47:33
1000	3	sciences_ai_026994d19509eb9820f7346778ccc2b2	026994d19509eb9820f7346778ccc2b2	vrai	sciences	10	2026-01-24 02:48:11	2026-01-24 02:48:11
1001	3	sciences_ai_5ace189d400917809b4030550b6db636	5ace189d400917809b4030550b6db636	299 792 458 m/s	sciences	10	2026-01-24 02:48:50	2026-01-24 02:48:50
1002	3	sciences_ai_b588c74c303897e9a17259a33a670604	b588c74c303897e9a17259a33a670604	faux	sciences	10	2026-01-24 02:49:29	2026-01-24 02:49:29
1003	3	sciences_ai_8c520bc7c7c84e8240bf511ec363063b	8c520bc7c7c84e8240bf511ec363063b	faux	sciences	10	2026-01-24 03:28:30	2026-01-24 03:28:30
1004	3	sciences_ai_bf0df3df0ddfec35df23cd73791d408a	bf0df3df0ddfec35df23cd73791d408a	becquerel	sciences	10	2026-01-24 03:29:03	2026-01-24 03:29:03
1005	3	sciences_ai_149aa203774a111ac6013ec987ed0448	149aa203774a111ac6013ec987ed0448	6.43 ev	sciences	10	2026-01-24 03:29:37	2026-01-24 03:29:37
1006	3	sciences_ai_3f4b03e23888f709cc3a986b1f3836e4	3f4b03e23888f709cc3a986b1f3836e4	la vitesse de la lumière	sciences	10	2026-01-24 03:30:16	2026-01-24 03:30:16
1007	3	sciences_ai_408aebdb08691987d24687bd287e8556	408aebdb08691987d24687bd287e8556	l'homo sapiens	sciences	10	2026-01-24 03:30:52	2026-01-24 03:30:52
1008	3	sciences_ai_5725083ad9acbade2194512fd58bd6fc	5725083ad9acbade2194512fd58bd6fc	verre brun	sciences	10	2026-01-24 03:31:28	2026-01-24 03:31:28
1009	3	sciences_ai_f04c0fa607cc8fbacd922a6834a93aa1	f04c0fa607cc8fbacd922a6834a93aa1	le photon	sciences	10	2026-01-24 03:32:04	2026-01-24 03:32:04
1010	3	sciences_ai_05bbc48de662486b49d7938026d9f2f9	05bbc48de662486b49d7938026d9f2f9	vrai	sciences	10	2026-01-24 03:32:33	2026-01-24 03:32:33
1011	3	sciences_ai_c9f5b70ba5a8a89a9aaad4a6ee4f14a0	c9f5b70ba5a8a89a9aaad4a6ee4f14a0	l'interaction forte	sciences	10	2026-01-24 03:33:03	2026-01-24 03:33:03
1012	3	sciences_ai_faddcc1ca8056de0d372537f56f3b355	faddcc1ca8056de0d372537f56f3b355	l'or	sciences	10	2026-01-24 03:33:40	2026-01-24 03:33:40
1013	3	sciences_ai_23571babec729dc71b7cebcb3c7a8cd0	23571babec729dc71b7cebcb3c7a8cd0	9,109 × 10⁻³¹ kg	sciences	10	2026-01-24 03:34:19	2026-01-24 03:34:19
1014	3	sciences_ai_ff0906bf7e9eb2851b5e789df5c9590f	ff0906bf7e9eb2851b5e789df5c9590f	faux	sciences	10	2026-01-24 03:34:54	2026-01-24 03:34:54
1015	3	sciences_ai_229593a4946173ead0cd6ee10e74b792	229593a4946173ead0cd6ee10e74b792	joule par kelvin (j/k)	sciences	10	2026-01-24 03:35:30	2026-01-24 03:35:30
1016	3	sciences_ai_04dbba372b311b7fe1287116eedd4a94	04dbba372b311b7fe1287116eedd4a94	vrai	sciences	10	2026-01-24 03:36:08	2026-01-24 03:36:08
1017	3	sciences_ai_b532297f1c42bf2fa5cd4b1874f3c777	b532297f1c42bf2fa5cd4b1874f3c777	299 792 458 m/s	sciences	10	2026-01-24 03:36:47	2026-01-24 03:36:47
1018	3	sciences_ai_b07cce7958560382a217869c724f58d6	b07cce7958560382a217869c724f58d6	environ 1100 kg/m³	sciences	10	2026-01-24 03:37:26	2026-01-24 03:37:26
1019	3	sciences_ai_18f62581fc406e8c1e606432c0151d07	18f62581fc406e8c1e606432c0151d07	la gravitation universelle	sciences	10	2026-01-24 03:38:05	2026-01-24 03:38:05
1020	3	sciences_ai_2dcef810b69cc4027e200cd345c174f7	2dcef810b69cc4027e200cd345c174f7	faux	sciences	10	2026-01-24 03:38:43	2026-01-24 03:38:43
1021	3	sciences_ai_712a7106972afbb4f3a397cc1cf399e4	712a7106972afbb4f3a397cc1cf399e4	le disque de zinc	sciences	10	2026-01-24 03:39:14	2026-01-24 03:39:14
1022	3	sciences_ai_dcae66dfa8e7ad6a3ed4d89de8de3545	dcae66dfa8e7ad6a3ed4d89de8de3545	faux	sciences	10	2026-01-24 03:39:55	2026-01-24 03:39:55
1023	3	general_ai_8bc17bc60b9bc893be70ad76e61dfa1a	8bc17bc60b9bc893be70ad76e61dfa1a	le tibre	general	10	2026-01-24 04:12:02	2026-01-24 04:12:02
1024	3	general_ai_3ba2d59b57e0620511bf12bcf4f88126	3ba2d59b57e0620511bf12bcf4f88126	lomé	general	10	2026-01-24 04:12:31	2026-01-24 04:12:31
1025	3	general_ai_9a5a80c30c71f5c043bf787faaa88f79	9a5a80c30c71f5c043bf787faaa88f79	le sulfate de calcium dihydraté	general	10	2026-01-24 04:13:07	2026-01-24 04:13:07
1026	3	general_ai_ef001300eea981cdcdd400548e470a3f	ef001300eea981cdcdd400548e470a3f	le lsd-25	general	10	2026-01-24 04:13:49	2026-01-24 04:13:49
1027	3	general_ai_6f2cda1248915e5a91c195a18e1316aa	6f2cda1248915e5a91c195a18e1316aa	la banque d'angleterre	general	10	2026-01-24 04:14:16	2026-01-24 04:14:16
1028	3	general_ai_ce4fde401b81c4138765c8028184f2d7	ce4fde401b81c4138765c8028184f2d7	isocyanate de méthyle	general	10	2026-01-24 04:14:55	2026-01-24 04:14:55
1029	3	general_ai_59d78504a66fc687ced6784b2d66ed39	59d78504a66fc687ced6784b2d66ed39	le big bang	general	10	2026-01-24 04:15:44	2026-01-24 04:15:44
1030	3	general_ai_420f183d61a4132b156bc16eaed664fe	420f183d61a4132b156bc16eaed664fe	constantin ier	general	10	2026-01-24 04:16:07	2026-01-24 04:16:07
1031	3	general_ai_62600f41ba04d3c6942e8e364820b6cf	62600f41ba04d3c6942e8e364820b6cf	faux	general	10	2026-01-24 04:16:45	2026-01-24 04:16:45
1032	3	general_ai_972373ed126c120387250063f629252d	972373ed126c120387250063f629252d	la convention de genève	general	10	2026-01-24 04:17:32	2026-01-24 04:17:32
1033	3	general_ai_310b7478333be495da5f433f94d80eef	310b7478333be495da5f433f94d80eef	tungstène	general	10	2026-01-24 04:18:05	2026-01-24 04:18:05
1034	3	general_ai_962853da3fe3c99b2e9af887f8cc8fd4	962853da3fe3c99b2e9af887f8cc8fd4	vrai	general	10	2026-01-24 04:18:48	2026-01-24 04:18:48
1035	3	general_ai_ff6a88b69126fb1c187fbb107ff43112	ff6a88b69126fb1c187fbb107ff43112	les médicis	general	10	2026-01-24 04:19:29	2026-01-24 04:19:29
1036	3	general_ai_65387cbfe3f3d589a8d285a0740f9d48	65387cbfe3f3d589a8d285a0740f9d48	fullerène c60	general	10	2026-01-24 04:19:51	2026-01-24 04:19:51
1037	3	general_ai_8570264e76423ed9fe0d67fd415f4ac9	8570264e76423ed9fe0d67fd415f4ac9	carbone 60	general	10	2026-01-24 04:20:21	2026-01-24 04:20:21
1038	3	general_ai_8dee7d2d740723e72528dc298061372e	8dee7d2d740723e72528dc298061372e	harold urey	general	10	2026-01-24 04:20:55	2026-01-24 04:20:55
1039	3	general_ai_53989c363d1604a74354bec48e1693f9	53989c363d1604a74354bec48e1693f9	l'iapt	general	10	2026-01-24 04:21:23	2026-01-24 04:21:23
1040	3	general_ai_6b190dce3fdbd10466f187a3b90b0e85	6b190dce3fdbd10466f187a3b90b0e85	georg friedrich haendel	general	10	2026-01-24 04:21:56	2026-01-24 04:21:56
1041	3	general_ai_42796170cc02f272ade23ce126a5d69b	42796170cc02f272ade23ce126a5d69b	la relativité restreinte	general	10	2026-01-24 04:22:35	2026-01-24 04:22:35
1042	3	general_ai_fdb82980490d5c5713d6dd9e42b26df2	fdb82980490d5c5713d6dd9e42b26df2	milan	general	10	2026-01-24 04:23:27	2026-01-24 04:23:27
1043	3	general_ai_225a2e831472556d03c4220aab4e6395	225a2e831472556d03c4220aab4e6395	le salvador	general	10	2026-01-24 04:27:53	2026-01-24 04:27:53
1044	3	general_ai_03141dd2432dd01a7eb19e4facca64bd	03141dd2432dd01a7eb19e4facca64bd	le chimpanzé	general	10	2026-01-24 04:28:31	2026-01-24 04:28:31
1045	3	general_ai_533cfe10fdf785551843e6431fade310	533cfe10fdf785551843e6431fade310	platon	general	10	2026-01-24 04:29:03	2026-01-24 04:29:03
1046	3	general_ai_966c4437bbedd144157d8b4525f0a902	966c4437bbedd144157d8b4525f0a902	aluminium	general	10	2026-01-24 04:29:54	2026-01-24 04:29:54
1047	3	general_ai_c7f3564a63524f8a9de64fc7e8704006	c7f3564a63524f8a9de64fc7e8704006	le traité de maastricht	general	10	2026-01-24 04:30:48	2026-01-24 04:30:48
1048	3	general_ai_e9c8861ba778e393d826f73174be7404	e9c8861ba778e393d826f73174be7404	kuiper airborne observatory	general	10	2026-01-24 04:31:41	2026-01-24 04:31:41
1049	3	general_ai_cd1f532850b14f477c5921a384cc9df6	cd1f532850b14f477c5921a384cc9df6	les traités de westphalie	general	10	2026-01-24 04:32:35	2026-01-24 04:32:35
1050	3	general_ai_fd72d5bb0e251577ba01ccbcaee7cd12	fd72d5bb0e251577ba01ccbcaee7cd12	john nash	general	10	2026-01-24 04:33:29	2026-01-24 04:33:29
1051	3	general_ai_ee0b4908358d538db0131fd4d718d2c8	ee0b4908358d538db0131fd4d718d2c8	rudolf clausius	general	10	2026-01-24 04:34:23	2026-01-24 04:34:23
1052	3	general_ai_117884717fce621dd2a1eef8afddfcc6	117884717fce621dd2a1eef8afddfcc6	faux	general	10	2026-01-24 04:35:17	2026-01-24 04:35:17
1053	3	general_ai_3f1c7039c92061d43039201716029c86	3f1c7039c92061d43039201716029c86	albert einstein	general	10	2026-01-24 04:36:10	2026-01-24 04:36:10
1054	3	general_ai_fbb65218657846b869a9b4fe07841b57	fbb65218657846b869a9b4fe07841b57	l'hémoglobine	general	10	2026-01-24 04:36:50	2026-01-24 04:36:50
1055	3	general_ai_b5fa7e9112d321bbbe516541c0d8274d	b5fa7e9112d321bbbe516541c0d8274d	la bléomycine	general	10	2026-01-24 11:18:47	2026-01-24 11:18:47
1056	3	general_ai_7bd96bf55bf5dbcdd453d990f632688a	7bd96bf55bf5dbcdd453d990f632688a	le carbonate de calcium	general	10	2026-01-24 11:19:37	2026-01-24 11:19:37
1057	3	general_ai_a834cf482a9c068b009a481df00aa33f	a834cf482a9c068b009a481df00aa33f	erwin schrödinger	general	10	2026-01-24 11:20:29	2026-01-24 11:20:29
1058	3	general_ai_223d3c254d5e3fc87f74fe2bf84edf46	223d3c254d5e3fc87f74fe2bf84edf46	vrai	general	10	2026-01-24 11:21:14	2026-01-24 11:21:14
1059	3	general_ai_9aec1221304d78d594e0a45fa7c46a9b	9aec1221304d78d594e0a45fa7c46a9b	piotr ilitch tchaïkovski	general	10	2026-01-24 11:22:02	2026-01-24 11:22:02
1060	3	general_ai_fe9a79529e8a2df814a37c1bf22e2de6	fe9a79529e8a2df814a37c1bf22e2de6	pierre et jacques curie	general	10	2026-01-24 11:22:28	2026-01-24 11:22:28
1061	3	general_ai_dbfb6401a645f057023d5605342f34b6	dbfb6401a645f057023d5605342f34b6	le droit à la représentation effective	general	10	2026-01-24 11:23:11	2026-01-24 11:23:11
1062	3	general_ai_9b3ef2eab30fdf0a83bd1cc760245340	9b3ef2eab30fdf0a83bd1cc760245340	le polonium	general	10	2026-01-24 12:48:36	2026-01-24 12:48:36
1063	3	general_ai_b66137b5a3eb97cc4d7a79e9a42e081e	b66137b5a3eb97cc4d7a79e9a42e081e	faux	general	10	2026-01-24 12:49:06	2026-01-24 12:49:06
1064	3	general_ai_b14812c68d2d074fdb8d62358ce334e1	b14812c68d2d074fdb8d62358ce334e1	james clerk maxwell	general	10	2026-01-24 12:49:32	2026-01-24 12:49:32
1065	3	general_ai_8d3cec1c2180207fae6921d2b4a5194b	8d3cec1c2180207fae6921d2b4a5194b	faux	general	10	2026-01-24 12:49:56	2026-01-24 12:49:56
1066	3	general_ai_b898473d1874728ba2a7d2bb1d4891fb	b898473d1874728ba2a7d2bb1d4891fb	la cellulose	general	10	2026-01-24 12:50:21	2026-01-24 12:50:21
1067	3	general_ai_167f55c85b1364fcd36a8819bff6c5fa	167f55c85b1364fcd36a8819bff6c5fa	le renard volant géant	general	10	2026-01-24 12:50:43	2026-01-24 12:50:43
1068	3	general_ai_dee9d16fc95c2314d89994255ed118ec	dee9d16fc95c2314d89994255ed118ec	il est construit en verre vert, symbolisant l'espoir et la paix.	general	10	2026-01-24 12:51:21	2026-01-24 12:51:21
1069	3	general_ai_983a9a9e03ef1237810efb32b632700a	983a9a9e03ef1237810efb32b632700a	vrai	general	10	2026-01-24 12:51:51	2026-01-24 12:51:51
1070	3	general_ai_2db4e1e5f2124aafa7a7931eb55a6af7	2db4e1e5f2124aafa7a7931eb55a6af7	traité d'ouchy	general	10	2026-01-24 12:52:08	2026-01-24 12:52:08
1071	3	faune_ai_5b8fed2a59fa812793ed7188a91f6197	5b8fed2a59fa812793ed7188a91f6197	faux	faune	10	2026-01-24 17:36:31	2026-01-24 17:36:31
1072	3	faune_ai_3b8b5b360d92c9d63bd5d84fe85af6a8	3b8b5b360d92c9d63bd5d84fe85af6a8	le bécasseau maubèche	faune	10	2026-01-24 18:37:39	2026-01-24 18:37:39
1073	3	faune_ai_44e6810224628f7402f31f3f7ce02f3e	44e6810224628f7402f31f3f7ce02f3e	le géospize pique-bois	faune	10	2026-01-24 18:38:08	2026-01-24 18:38:08
1074	3	faune_ai_3f2e5e06ebc8a7d8b47bf1f9daa54605	3f2e5e06ebc8a7d8b47bf1f9daa54605	la barge rousse	faune	10	2026-01-24 18:38:29	2026-01-24 18:38:29
1075	3	faune_ai_68198041d395cc9f0672f173d457cf29	68198041d395cc9f0672f173d457cf29	la punaise triatome	faune	10	2026-01-24 18:39:19	2026-01-24 18:39:19
1076	3	faune_ai_766f5c7a589aec3ffeb88aca7f9eb9f2	766f5c7a589aec3ffeb88aca7f9eb9f2	vrai	faune	10	2026-01-24 18:40:13	2026-01-24 18:40:13
1077	3	cuisine_ai_3a812eafc208da78337fc1f8e89a2bce	3a812eafc208da78337fc1f8e89a2bce	l'ochratoxine a	cuisine	10	2026-01-24 19:26:14	2026-01-24 19:26:14
1078	3	cuisine_ai_b4d5cdf185b8a2bf30439daaaff4ce05	b4d5cdf185b8a2bf30439daaaff4ce05	la noix de muscade	cuisine	10	2026-01-24 19:27:02	2026-01-24 19:27:02
1079	3	cuisine_ai_920c08ee3a27cce2225f43844c8cd2e7	920c08ee3a27cce2225f43844c8cd2e7	le chocolat	cuisine	10	2026-01-24 19:27:42	2026-01-24 19:27:42
1080	3	cuisine_ai_c4993bcacbcd28dd5d353fc053be33a6	c4993bcacbcd28dd5d353fc053be33a6	le cognac	cuisine	10	2026-01-24 19:28:24	2026-01-24 19:28:24
1081	3	cuisine_ai_f74bf034d2e072d648037ec130d96d8e	f74bf034d2e072d648037ec130d96d8e	faux	cuisine	10	2026-01-24 19:28:59	2026-01-24 19:28:59
1082	3	cuisine_ai_9b6fc36a8a6afa9d6bc10597b03a34fa	9b6fc36a8a6afa9d6bc10597b03a34fa	la sologne	cuisine	10	2026-01-24 19:29:19	2026-01-24 19:29:19
1083	3	cuisine_ai_ccf37eac345e889bdf6c4f7ca852b8af	ccf37eac345e889bdf6c4f7ca852b8af	le vin rouge	cuisine	10	2026-01-24 19:29:51	2026-01-24 19:29:51
1084	3	cuisine_ai_9b9452250ef295c3e09309da7d750950	9b9452250ef295c3e09309da7d750950	pêche melba	cuisine	10	2026-01-24 19:30:14	2026-01-24 19:30:14
1085	3	cuisine_ai_2549ad863e1aeb7e25e05ea9f8edc729	2549ad863e1aeb7e25e05ea9f8edc729	faux	cuisine	10	2026-01-24 19:30:37	2026-01-24 19:30:37
1086	3	cuisine_ai_1921d65b2acf62762da2e0f10291da9f	1921d65b2acf62762da2e0f10291da9f	pol roger	cuisine	10	2026-01-24 19:31:02	2026-01-24 19:31:02
1087	3	cuisine_ai_ee99cc9a78d7930274efb6af2ba8d38b	ee99cc9a78d7930274efb6af2ba8d38b	vrai	cuisine	10	2026-01-24 19:31:36	2026-01-24 19:31:36
1088	3	cuisine_ai_8e1365ee70b96cacff3f902c87295bb2	8e1365ee70b96cacff3f902c87295bb2	bleu de septmoncel	cuisine	10	2026-01-24 19:32:01	2026-01-24 19:32:01
1089	3	cuisine_ai_cbded2840a94ef0b0ea318c3e5484cd6	cbded2840a94ef0b0ea318c3e5484cd6	italien, de « maccherone »	cuisine	10	2026-01-24 19:32:20	2026-01-24 19:32:20
1090	3	cuisine_ai_20740c8957deb0c6c615cce068055373	20740c8957deb0c6c615cce068055373	un mot italien signifiant 'pâte fine'	cuisine	10	2026-01-24 19:32:47	2026-01-24 19:32:47
1091	3	cuisine_ai_4f1336cf8cd5b7dbb29a9cf2d3ed2838	4f1336cf8cd5b7dbb29a9cf2d3ed2838	vrai	cuisine	10	2026-01-24 19:33:13	2026-01-24 19:33:13
1092	3	cuisine_ai_a6fc89f371d8352106fd660429f53a15	a6fc89f371d8352106fd660429f53a15	tétrodotoxine	cuisine	10	2026-01-24 19:33:36	2026-01-24 19:33:36
1093	3	cuisine_ai_a646b64c0fd1ef1a8ead99d0968d53aa	a646b64c0fd1ef1a8ead99d0968d53aa	châteauneuf-du-pape	cuisine	10	2026-01-24 19:34:01	2026-01-24 19:34:01
1094	3	cuisine_ai_2458f0d6a3751a9c8f0d4237171bd5c9	2458f0d6a3751a9c8f0d4237171bd5c9	le xérès	cuisine	10	2026-01-24 19:34:21	2026-01-24 19:34:21
1095	3	cuisine_ai_ac0e6b6734af54c010f4a136b092987d	ac0e6b6734af54c010f4a136b092987d	saké	cuisine	10	2026-01-24 19:35:05	2026-01-24 19:35:05
1096	3	cuisine_ai_666087495b0ad24d59c403037cbf20b4	666087495b0ad24d59c403037cbf20b4	faux	cuisine	10	2026-01-24 19:35:59	2026-01-24 19:35:59
1097	3	general_ai_e77da30eab553a700860e6c875b63515	e77da30eab553a700860e6c875b63515	le cheval	general	7	2026-01-26 09:13:37	2026-01-26 09:13:37
1098	3	general_ai_8e0186b41210703a319e57a1818d8dd9	8e0186b41210703a319e57a1818d8dd9	environ 8 minutes et 20 secondes	general	7	2026-01-26 09:14:13	2026-01-26 09:14:13
1099	3	general_ai_def3769b358e58570138152f5ba769ec	def3769b358e58570138152f5ba769ec	thomas edison	general	7	2026-01-26 09:15:07	2026-01-26 09:15:07
1100	3	general_ai_e6a993b809b457c0d5357061cc46a7fa	e6a993b809b457c0d5357061cc46a7fa	faux	general	7	2026-01-26 09:16:00	2026-01-26 09:16:00
1101	3	general_ai_dd14f184929cf9e3051571330ee394a0	dd14f184929cf9e3051571330ee394a0	l'argent	general	7	2026-01-26 09:16:37	2026-01-26 09:16:37
1102	3	general_ai_04100e061a7e3666ad1f8e4660f5b4c1	04100e061a7e3666ad1f8e4660f5b4c1	la suède	general	7	2026-01-26 09:17:28	2026-01-26 09:17:28
1103	3	general_ai_5609a9791ecccdc45677affcfbb1b773	5609a9791ecccdc45677affcfbb1b773	le danube	general	7	2026-01-26 09:18:07	2026-01-26 09:18:07
1104	3	general_ai_0b44b14d25336d2e35863c2f683992ed	0b44b14d25336d2e35863c2f683992ed	vrai	general	7	2026-01-26 09:18:42	2026-01-26 09:18:42
1105	3	general_ai_bdbe6472fa7a0a8aebc9f52f3da10858	bdbe6472fa7a0a8aebc9f52f3da10858	les États-unis	general	7	2026-01-26 09:19:20	2026-01-26 09:19:20
1106	3	general_ai_539584ffb0c9f913cac8f97121397d4c	539584ffb0c9f913cac8f97121397d4c	genève	general	7	2026-01-26 09:20:00	2026-01-26 09:20:00
1107	3	general_ai_82755aa554acc69b33b7048c9e5657e9	82755aa554acc69b33b7048c9e5657e9	vrai	general	7	2026-01-26 11:25:16	2026-01-26 11:25:16
1108	3	general_ai_8ea3c2e26888ae23a3591694492f2851	8ea3c2e26888ae23a3591694492f2851	le pygargue à tête blanche	general	7	2026-01-26 11:25:45	2026-01-26 11:25:45
1109	3	general_ai_f5be228df741da10bec58c654df41156	f5be228df741da10bec58c654df41156	le koala	general	7	2026-01-26 11:26:20	2026-01-26 11:26:20
1110	3	general_ai_c2d14c326605753694db341a7f910e73	c2d14c326605753694db341a7f910e73	la girafe	general	7	2026-01-26 11:26:52	2026-01-26 11:26:52
1111	3	general_ai_b90b1dbd8a85bdc373c695af6848e39f	b90b1dbd8a85bdc373c695af6848e39f	jupiter	general	7	2026-01-26 11:27:13	2026-01-26 11:27:13
1112	3	general_ai_ff3a6e310d462c452d7dd728590b6234	ff3a6e310d462c452d7dd728590b6234	tokyo	general	7	2026-01-26 11:27:34	2026-01-26 11:27:34
1113	3	general_ai_77e2de98dfc749cfad12ecca3c6a1457	77e2de98dfc749cfad12ecca3c6a1457	l'éléphant	general	7	2026-01-26 11:28:23	2026-01-26 11:28:23
1114	3	general_ai_c2943b1a3ca94585afe8dcd3a536bb78	c2943b1a3ca94585afe8dcd3a536bb78	le perce-neige	general	7	2026-01-26 11:29:17	2026-01-26 11:29:17
1115	3	general_ai_8d507c7f6271bfbf90f91a91240e5515	8d507c7f6271bfbf90f91a91240e5515	l'océan atlantique	general	7	2026-01-26 11:30:11	2026-01-26 11:30:11
1116	3	general_ai_8c79f5173229554f4581adf7cae173d6	8c79f5173229554f4581adf7cae173d6	le zèbre	general	7	2026-01-26 11:31:05	2026-01-26 11:31:05
1117	3	general_ai_9b6ed0434ba504a5e6a0d3cbbb4f17dc	9b6ed0434ba504a5e6a0d3cbbb4f17dc	le dniepr	general	7	2026-01-26 11:50:18	2026-01-26 11:50:18
1118	3	general_ai_f0083f2ea34f1792b7e2d04fab328947	f0083f2ea34f1792b7e2d04fab328947	netscape navigator	general	7	2026-01-26 11:50:50	2026-01-26 11:50:50
1119	3	general_ai_a88ce2e8999f74bd34c6c02e4b404cb1	a88ce2e8999f74bd34c6c02e4b404cb1	le zèbre	general	7	2026-01-26 11:51:32	2026-01-26 11:51:32
1120	3	general_ai_aeb71f11e2e12963a0e84140edff581d	aeb71f11e2e12963a0e84140edff581d	rouge-orangé	general	7	2026-01-26 11:52:19	2026-01-26 11:52:19
1121	3	general_ai_0c56759abdf9fbc6bca6a57520379418	0c56759abdf9fbc6bca6a57520379418	vitamine d	general	7	2026-01-26 11:52:46	2026-01-26 11:52:46
1122	3	general_ai_b0bf2c08e9959ee9a046ece1e25bb187	b0bf2c08e9959ee9a046ece1e25bb187	neil armstrong	general	7	2026-01-26 11:53:21	2026-01-26 11:53:21
1123	3	general_ai_69b7bd65b7c00f0935536fccb02fcb22	69b7bd65b7c00f0935536fccb02fcb22	le cuivre	general	7	2026-01-26 11:53:44	2026-01-26 11:53:44
1124	3	general_ai_cafe5c2838bf4f324847c704e5374f7a	cafe5c2838bf4f324847c704e5374f7a	rouge et blanc	general	7	2026-01-26 11:54:10	2026-01-26 11:54:10
1125	3	general_ai_5940d53f8d6941075366ea4504ed55df	5940d53f8d6941075366ea4504ed55df	rose	general	7	2026-01-26 11:54:31	2026-01-26 11:54:31
1126	3	general_ai_0a985554eac8028e575b52cd12dea940	0a985554eac8028e575b52cd12dea940	l'éléphant d'afrique	general	7	2026-01-26 11:54:51	2026-01-26 11:54:51
1127	3	general_ai_3ca812276f60848d3312e0f1c0ebbbf6	3ca812276f60848d3312e0f1c0ebbbf6	faux	general	7	2026-01-26 14:31:02	2026-01-26 14:31:02
1128	3	general_ai_eb21e252164845d17b34c83bccb5b88e	eb21e252164845d17b34c83bccb5b88e	le yen	general	7	2026-01-26 14:31:56	2026-01-26 14:31:56
1129	3	general_ai_1a9877b02aeb9bd2c17b43941ad7d76e	1a9877b02aeb9bd2c17b43941ad7d76e	faux	general	7	2026-01-26 14:32:50	2026-01-26 14:32:50
1130	3	general_ai_cf48efa6b0500b7110d9d9aec7785303	cf48efa6b0500b7110d9d9aec7785303	l'italie	general	7	2026-01-26 14:33:45	2026-01-26 14:33:45
1131	3	general_ai_27ee6f8c91ccb1959630f597d7512254	27ee6f8c91ccb1959630f597d7512254	la france	general	7	2026-01-26 14:34:39	2026-01-26 14:34:39
1132	3	general_ai_442e5291658dfdb0d93f353639799369	442e5291658dfdb0d93f353639799369	les États-unis	general	7	2026-01-26 14:35:33	2026-01-26 14:35:33
1133	3	general_ai_74e0e798e61b992e137dacb8615f1661	74e0e798e61b992e137dacb8615f1661	le sable	general	7	2026-01-26 14:36:27	2026-01-26 14:36:27
1134	3	general_ai_e4efa589ecac9938ac8a246b23b03392	e4efa589ecac9938ac8a246b23b03392	le tigre	general	7	2026-01-26 14:37:32	2026-01-26 14:37:32
1135	3	general_ai_41eb8f2776c36bd9691cc35f4e71eb65	41eb8f2776c36bd9691cc35f4e71eb65	le léopard	general	7	2026-01-26 14:38:28	2026-01-26 14:38:28
1136	3	general_ai_40b71cbe073ca0f9ce38a4fcda07dbda	40b71cbe073ca0f9ce38a4fcda07dbda	l'indonésie	general	7	2026-01-26 14:39:24	2026-01-26 14:39:24
1137	3	general_ai_567553a77ff2c2b58ef46945ccc7b21f	567553a77ff2c2b58ef46945ccc7b21f	le lys	general	7	2026-01-26 14:40:16	2026-01-26 14:40:16
1138	3	general_ai_83ea92114894b67f28cb67703b345f4f	83ea92114894b67f28cb67703b345f4f	la seine	general	7	2026-01-26 14:40:42	2026-01-26 14:40:42
1139	3	general_ai_a0d96e1d8ad45e6d2edf7a1d26c908e2	a0d96e1d8ad45e6d2edf7a1d26c908e2	le mérou	general	7	2026-01-26 14:42:22	2026-01-26 14:42:22
1140	3	general_ai_e1bfd2f89e27c5f903d72b1eb83c5f58	e1bfd2f89e27c5f903d72b1eb83c5f58	l'autruche	general	7	2026-01-26 14:43:16	2026-01-26 14:43:16
1141	3	general_ai_c5060e57a0fd89c87899147f41e39f0a	c5060e57a0fd89c87899147f41e39f0a	l'argent	general	7	2026-01-26 14:44:10	2026-01-26 14:44:10
1142	3	general_ai_2b8b88bfdadd705fb5a119e2dd53380b	2b8b88bfdadd705fb5a119e2dd53380b	l'himalaya	general	7	2026-01-26 14:44:48	2026-01-26 14:44:48
1143	3	general_ai_fc6aa44b174f19e2b92ae06aca428513	fc6aa44b174f19e2b92ae06aca428513	le groenland	general	7	2026-01-26 14:45:12	2026-01-26 14:45:12
1144	3	general_ai_f4f42a737a06a6e0e8552ac104290af6	f4f42a737a06a6e0e8552ac104290af6	le rat kangourou	general	7	2026-01-26 14:45:30	2026-01-26 14:45:30
1145	3	general_ai_388eff1372497a86a955f7b7cdd6dc83	388eff1372497a86a955f7b7cdd6dc83	le lamantin	general	7	2026-01-26 14:46:05	2026-01-26 14:46:05
1146	3	general_ai_a4699008aad6d54017599e0257fa92ee	a4699008aad6d54017599e0257fa92ee	le tigre	general	7	2026-01-26 14:46:41	2026-01-26 14:46:41
1147	3	general_ai_9bb406aa915c96890f3c4a1ae405cab7	9bb406aa915c96890f3c4a1ae405cab7	elle est recouverte de petites épines orientées vers l'arrière.	general	7	2026-01-26 14:46:56	2026-01-26 14:46:56
1148	3	general_ai_db03b4b8ce011abbe2c07bcfa4abc8ce	db03b4b8ce011abbe2c07bcfa4abc8ce	le rhin	general	7	2026-01-26 14:47:24	2026-01-26 14:47:24
1149	3	general_ai_a0b0d61789efc754f80ac6d7a91a4026	a0b0d61789efc754f80ac6d7a91a4026	la baleine bleue	general	7	2026-01-26 14:48:08	2026-01-26 14:48:08
1150	3	general_ai_32decb4e1d63120e75952ffdcc9d1e99	32decb4e1d63120e75952ffdcc9d1e99	le colibri	general	7	2026-01-26 14:48:29	2026-01-26 14:48:29
1151	3	general_ai_4077080c827aeadc413ddebb9e493771	4077080c827aeadc413ddebb9e493771	la baleine à bosse	general	7	2026-01-26 14:49:05	2026-01-26 14:49:05
1152	3	general_ai_3ef7bc401b7bad8c7003a908df0a0510	3ef7bc401b7bad8c7003a908df0a0510	la gutta-percha	general	7	2026-01-26 14:49:42	2026-01-26 14:49:42
1153	3	general_ai_c750df5497b6ccd427ebb92a89f8f208	c750df5497b6ccd427ebb92a89f8f208	le guépard	general	7	2026-01-26 14:50:34	2026-01-26 14:50:34
1154	3	general_ai_407ca1422794bea2c095557fd885ed8c	407ca1422794bea2c095557fd885ed8c	le chêne	general	7	2026-01-26 14:50:54	2026-01-26 14:50:54
1155	3	general_ai_a5d9e84a11c3555a3f5e7dca18ea9ff1	a5d9e84a11c3555a3f5e7dca18ea9ff1	la chine	general	7	2026-01-26 14:51:14	2026-01-26 14:51:14
1156	3	general_ai_dc949c1a062eb89736e59e4c683bc6e4	dc949c1a062eb89736e59e4c683bc6e4	roald amundsen	general	12	2026-01-26 15:01:36	2026-01-26 15:01:36
1157	3	general_ai_9cdd54764505ec696c6d38b1b0553f09	9cdd54764505ec696c6d38b1b0553f09	mercure	general	12	2026-01-26 15:02:30	2026-01-26 15:02:30
1158	3	general_ai_1022a58e41a3d2bba1c70282316576ec	1022a58e41a3d2bba1c70282316576ec	wolfgang amadeus mozart	general	12	2026-01-26 15:03:24	2026-01-26 15:03:24
1159	3	general_ai_4df3c966bd6cd576728797d6f603e60d	4df3c966bd6cd576728797d6f603e60d	la poule	general	12	2026-01-26 15:03:57	2026-01-26 15:03:57
1160	3	general_ai_644851f7487773829d14f286d796188b	644851f7487773829d14f286d796188b	1963	general	12	2026-01-27 12:32:05	2026-01-27 12:32:05
1161	3	general_ai_c8dc99de75900e3c21f1d8d7e926c6c0	c8dc99de75900e3c21f1d8d7e926c6c0	l'éléphant d'afrique	general	12	2026-01-27 12:33:10	2026-01-27 12:33:10
1162	3	general_ai_8bc0b3cc9718b0ca6f79828e0af61eac	8bc0b3cc9718b0ca6f79828e0af61eac	vrai	general	12	2026-01-27 12:33:44	2026-01-27 12:33:44
1163	3	general_ai_71d8d9fdf60bed8eab39f555da9c42f4	71d8d9fdf60bed8eab39f555da9c42f4	1927	general	12	2026-01-27 12:34:21	2026-01-27 12:34:21
1164	3	general_ai_9b017b786691429d47a8a5030563234c	9b017b786691429d47a8a5030563234c	le caribou	general	12	2026-01-27 12:35:01	2026-01-27 12:35:01
1165	3	general_ai_e17794a1409b50db2408099c8054bdd5	e17794a1409b50db2408099c8054bdd5	o négatif	general	12	2026-01-27 12:35:46	2026-01-27 12:35:46
1166	3	general_ai_24309ff7bf9753c6f822acfe2bc1964a	24309ff7bf9753c6f822acfe2bc1964a	le rossignol	general	12	2026-01-27 12:36:19	2026-01-27 12:36:19
1167	3	general_ai_188026cd9c1f73c66f8e3be35b217ef2	188026cd9c1f73c66f8e3be35b217ef2	le koala	general	12	2026-01-27 12:37:14	2026-01-27 12:37:14
1168	3	general_ai_dfa73936ac1d5f99a85f6485b52a1fc5	dfa73936ac1d5f99a85f6485b52a1fc5	la chine	general	12	2026-01-27 12:38:08	2026-01-27 12:38:08
1169	3	general_ai_595daf37e5e3f11b60fa246054fae6f7	595daf37e5e3f11b60fa246054fae6f7	bleu	general	12	2026-01-27 12:39:02	2026-01-27 12:39:02
1170	3	general_ai_6aadd2ae2e28fbccea8e8ee4b99fbdbc	6aadd2ae2e28fbccea8e8ee4b99fbdbc	le morse	general	12	2026-01-27 13:41:23	2026-01-27 13:41:23
1171	3	general_ai_a27911541f35d7f402f2f573b41d09c7	a27911541f35d7f402f2f573b41d09c7	le méthane	general	12	2026-01-27 13:42:17	2026-01-27 13:42:17
1172	3	general_ai_039be38dddbfa9d7cbf103ba592796be	039be38dddbfa9d7cbf103ba592796be	faux	general	12	2026-01-27 13:43:11	2026-01-27 13:43:11
1173	3	general_ai_72fea253d349c2a7708da9b47c1b9d05	72fea253d349c2a7708da9b47c1b9d05	l'océan atlantique	general	12	2026-01-27 13:44:05	2026-01-27 13:44:05
1174	3	general_ai_f8e3941062db74086cf58d99cc00ec6e	f8e3941062db74086cf58d99cc00ec6e	le royaume-uni	general	12	2026-01-27 13:46:20	2026-01-27 13:46:20
1175	3	general_ai_8aba80d0958564ea3a533f498666d96c	8aba80d0958564ea3a533f498666d96c	la france	general	12	2026-01-27 13:47:18	2026-01-27 13:47:18
1176	3	general_ai_6e44523cfd6c89392e21f2ccd0207f4b	6e44523cfd6c89392e21f2ccd0207f4b	le mouton	general	12	2026-01-27 13:48:13	2026-01-27 13:48:13
1177	3	general_ai_911b8f855f79215f6c26f6125e058577	911b8f855f79215f6c26f6125e058577	le fer	general	12	2026-01-27 13:49:07	2026-01-27 13:49:07
1178	3	general_ai_ccef2c10f883c539466592f3671c12d5	ccef2c10f883c539466592f3671c12d5	les États-unis	general	12	2026-01-27 13:50:01	2026-01-27 13:50:01
1179	3	general_ai_8204b7ef2dd2acbb15bb6596c51d85ff	8204b7ef2dd2acbb15bb6596c51d85ff	l'osque	general	12	2026-01-27 13:50:55	2026-01-27 13:50:55
1180	3	general_ai_bbd81610fa84f73b491cd76c41a3d983	bbd81610fa84f73b491cd76c41a3d983	vincent van gogh	general	12	2026-01-28 13:27:33	2026-01-28 13:27:33
1181	3	general_ai_ba29874bd1ca0b5dc5a20e6686a47a8e	ba29874bd1ca0b5dc5a20e6686a47a8e	le dauphin tacheté	general	12	2026-01-28 13:28:05	2026-01-28 13:28:05
1182	3	general_ai_1a3764bb390455e21c90bdcc88008c7c	1a3764bb390455e21c90bdcc88008c7c	faux	general	12	2026-01-28 13:28:40	2026-01-28 13:28:40
1183	3	general_ai_a89b4b63ce95f2a8746c8cddffef9a06	a89b4b63ce95f2a8746c8cddffef9a06	océan arctique	general	12	2026-01-28 13:29:08	2026-01-28 13:29:08
1184	3	general_ai_4c430be2b6329edf6b293a2c5c46b5ad	4c430be2b6329edf6b293a2c5c46b5ad	argent	general	12	2026-01-28 13:30:01	2026-01-28 13:30:01
1185	3	general_ai_1d3ebf064e42d1866f5bd542a9b4ffdf	1d3ebf064e42d1866f5bd542a9b4ffdf	la russie	general	12	2026-01-28 13:30:20	2026-01-28 13:30:20
1186	3	general_ai_ed76d3540b3bc58b7f46bc24141a0ea1	ed76d3540b3bc58b7f46bc24141a0ea1	la vltava	general	12	2026-01-28 13:31:09	2026-01-28 13:31:09
1187	3	general_ai_b1b2f36150543a8193daf33fc32b5f7f	b1b2f36150543a8193daf33fc32b5f7f	le mexique	general	12	2026-01-28 13:31:54	2026-01-28 13:31:54
1188	3	general_ai_39ca575996683dcfc5aaf20926baf4fe	39ca575996683dcfc5aaf20926baf4fe	le cachalot	general	12	2026-01-28 13:32:32	2026-01-28 13:32:32
1189	3	general_ai_31f7b43eb853095da62860421c325379	31f7b43eb853095da62860421c325379	l'Éthiopie	general	12	2026-01-28 13:33:05	2026-01-28 13:33:05
1190	3	general_ai_0c31244bafede6cf7970027b6c9990d8	0c31244bafede6cf7970027b6c9990d8	la suède	general	12	2026-01-28 13:33:32	2026-01-28 13:33:32
1191	3	general_ai_5b0dfd848186028d962ee149176c2959	5b0dfd848186028d962ee149176c2959	james lovell	general	12	2026-01-28 13:34:22	2026-01-28 13:34:22
1192	3	general_ai_6fb3e610814ecd5525b270db01416e91	6fb3e610814ecd5525b270db01416e91	le bronze	general	12	2026-01-28 13:35:16	2026-01-28 13:35:16
1193	3	general_ai_52e36e81738bcd500ab7c3e55b9a70b8	52e36e81738bcd500ab7c3e55b9a70b8	sicile	general	12	2026-01-28 13:36:10	2026-01-28 13:36:10
1194	3	general_ai_e9ba0fa1b6767f14b669070549928787	e9ba0fa1b6767f14b669070549928787	l'indonésie	general	12	2026-01-28 13:37:04	2026-01-28 13:37:04
1195	3	general_ai_1951dfbff22928af835f07b6c490312c	1951dfbff22928af835f07b6c490312c	la baleine à bosse	general	12	2026-01-28 13:37:58	2026-01-28 13:37:58
1196	3	general_ai_e3a739be8dac2dd6377def6ef021ac8f	e3a739be8dac2dd6377def6ef021ac8f	le caméléon	general	12	2026-01-28 13:38:52	2026-01-28 13:38:52
1197	3	general_ai_1797ce84242f4fc78dc7c26dfd28217b	1797ce84242f4fc78dc7c26dfd28217b	le cèdre	general	12	2026-01-28 13:39:45	2026-01-28 13:39:45
1198	3	general_ai_eb8d0335c6129e8bcedbc60b315d2b60	eb8d0335c6129e8bcedbc60b315d2b60	la luciole	general	12	2026-01-28 13:40:39	2026-01-28 13:40:39
1199	3	general_ai_ccfaaaa6fc4681c8c4aba50d75989e5c	ccfaaaa6fc4681c8c4aba50d75989e5c	le sulfate de calcium	general	12	2026-01-28 13:41:33	2026-01-28 13:41:33
1200	3	general_ai_f937552d613c61484aa4494a7175f4bb	f937552d613c61484aa4494a7175f4bb	l'autruche	general	29	2026-01-29 15:52:47	2026-01-29 15:52:47
1201	3	general_ai_fe2bed2cfa3f97ebaef08895a66b1134	fe2bed2cfa3f97ebaef08895a66b1134	le kiwi	general	29	2026-01-29 15:53:20	2026-01-29 15:53:20
1202	3	general_ai_d33d01603550cb02ed20fe0136554270	d33d01603550cb02ed20fe0136554270	arcangelo corelli	general	29	2026-01-29 15:54:09	2026-01-29 15:54:09
1203	3	general_ai_70a75f9698db0206446ec2f8ca4512b9	70a75f9698db0206446ec2f8ca4512b9	l'hélium	general	29	2026-01-29 15:55:37	2026-01-29 15:55:37
1204	3	general_ai_5756085ef7f7790b3206b1b36b394673	5756085ef7f7790b3206b1b36b394673	claude monet	general	29	2026-01-29 15:56:15	2026-01-29 15:56:15
1205	3	general_ai_46877364829de821e05c2eb1324eff77	46877364829de821e05c2eb1324eff77	faux	general	29	2026-01-29 15:56:54	2026-01-29 15:56:54
1206	3	general_ai_f7ff21ed84915721eb82b09349b68310	f7ff21ed84915721eb82b09349b68310	vrai	general	29	2026-01-29 15:57:26	2026-01-29 15:57:26
1207	3	general_ai_4a7b2a415df821412ed46f3c2e25cd99	4a7b2a415df821412ed46f3c2e25cd99	la locomotive à vapeur	general	29	2026-01-29 15:57:55	2026-01-29 15:57:55
1208	3	general_ai_819823145d747839bcb3924cfd17ec4d	819823145d747839bcb3924cfd17ec4d	le tungstène	general	29	2026-01-29 15:58:34	2026-01-29 15:58:34
1209	3	general_ai_4200d4bcc057f2c189c7ca6683903bbc	4200d4bcc057f2c189c7ca6683903bbc	lagos	general	29	2026-01-29 15:59:26	2026-01-29 15:59:26
1210	3	general_ai_8baaf2ba7fc8889ccb4c2951d1b633a8	8baaf2ba7fc8889ccb4c2951d1b633a8	indonésie	general	29	2026-01-29 15:59:53	2026-01-29 15:59:53
1211	3	general_ai_f5a3f24c47e073134f1bf7eaed4a4f4d	f5a3f24c47e073134f1bf7eaed4a4f4d	nirvana	general	29	2026-01-29 16:00:44	2026-01-29 16:00:44
1212	3	general_ai_84c9e5ea2bbd15f5a199fd47414d84c8	84c9e5ea2bbd15f5a199fd47414d84c8	le calcium	general	29	2026-01-29 16:01:38	2026-01-29 16:01:38
1213	3	general_ai_95c363f3175197f8144a249d49a2e51a	95c363f3175197f8144a249d49a2e51a	claude monet	general	29	2026-01-29 16:10:31	2026-01-29 16:10:31
1214	3	general_ai_72fe82f831443d9c85c54376a55a3adc	72fe82f831443d9c85c54376a55a3adc	les pays-bas	general	29	2026-01-29 16:11:14	2026-01-29 16:11:14
1215	3	general_ai_4c7e8154a33f016628a2f1806c7e8caf	4c7e8154a33f016628a2f1806c7e8caf	l'azote	general	29	2026-01-29 16:11:39	2026-01-29 16:11:39
1216	3	general_ai_17b0a28b5f4eeb2caf4344953a625f33	17b0a28b5f4eeb2caf4344953a625f33	la tortue géante	general	29	2026-01-29 16:12:27	2026-01-29 16:12:27
1217	3	general_ai_53a3e897024e2e1440efae5e8e7e186a	53a3e897024e2e1440efae5e8e7e186a	1967	general	29	2026-01-29 16:13:07	2026-01-29 16:13:07
1218	3	general_ai_5f7aa4f7639f2966a0bf15bcbb39388b	5f7aa4f7639f2966a0bf15bcbb39388b	l'ours noir	general	28	2026-02-03 12:04:35	2026-02-03 12:04:35
1219	3	general_ai_629697a081e56ebf610d6e6abd416e27	629697a081e56ebf610d6e6abd416e27	l'union soviétique	general	28	2026-02-03 12:05:07	2026-02-03 12:05:07
1220	3	general_ai_c2e80595f02677db539a70b6b9a4c305	c2e80595f02677db539a70b6b9a4c305	le palladium	general	28	2026-02-03 12:05:44	2026-02-03 12:05:44
1221	3	general_ai_3db51f9aae4e7eb1ae93a4a0609f898d	3db51f9aae4e7eb1ae93a4a0609f898d	spacex	general	28	2026-02-03 12:06:28	2026-02-03 12:06:28
1222	3	general_ai_4602337373201c922d8a19377ad3f93b	4602337373201c922d8a19377ad3f93b	marc chagall	general	28	2026-02-03 12:07:04	2026-02-03 12:07:04
1223	3	general_ai_40445af9f54794f995620941cded03f7	40445af9f54794f995620941cded03f7	le danube	general	28	2026-02-03 12:07:42	2026-02-03 12:07:42
1224	3	general_ai_8ae4dc65ba062c681c4d70b72239a72a	8ae4dc65ba062c681c4d70b72239a72a	l'allemagne	general	28	2026-02-03 12:08:27	2026-02-03 12:08:27
1225	3	general_ai_36140d7e2248493ce72b039cbd36b32c	36140d7e2248493ce72b039cbd36b32c	la sélénite	general	28	2026-02-03 12:09:01	2026-02-03 12:09:01
1226	3	general_ai_26ee6f833363ab8c1e91135dbb521f69	26ee6f833363ab8c1e91135dbb521f69	grenoble	general	28	2026-02-03 12:09:42	2026-02-03 12:09:42
1227	3	general_ai_936b7ef06eced391a492ba319e2594ec	936b7ef06eced391a492ba319e2594ec	l'iguane	general	28	2026-02-03 12:10:20	2026-02-03 12:10:20
1228	3	general_ai_5d39f13999437291f1d671498a81dd59	5d39f13999437291f1d671498a81dd59	océan atlantique	general	28	2026-02-03 12:11:57	2026-02-03 12:11:57
1229	3	general_ai_05abed0893706a5d8462044ab41ff501	05abed0893706a5d8462044ab41ff501	vrai	general	28	2026-02-03 12:12:39	2026-02-03 12:12:39
1230	3	general_ai_44139c463693ee496436bf0a6144e6b1	44139c463693ee496436bf0a6144e6b1	ferdinand magellan	general	28	2026-02-03 12:13:27	2026-02-03 12:13:27
1231	3	general_ai_cf841e5c935337e2a6392bb8134a841e	cf841e5c935337e2a6392bb8134a841e	faux	general	28	2026-02-03 12:13:51	2026-02-03 12:13:51
1232	3	general_ai_d2433e64fe57d4bc1fa8b4073ea05346	d2433e64fe57d4bc1fa8b4073ea05346	france	general	28	2026-02-03 12:14:24	2026-02-03 12:14:24
1233	3	general_ai_983ebc3d88d392b4e267dad4131b5b25	983ebc3d88d392b4e267dad4131b5b25	l'arno	general	28	2026-02-03 12:15:02	2026-02-03 12:15:02
1234	3	general_ai_320f410d0997c9fcbf03dd9a690d360e	320f410d0997c9fcbf03dd9a690d360e	l'urss	general	28	2026-02-03 12:15:32	2026-02-03 12:15:32
1235	3	general_ai_d72218c7b227eccaf9006be28930ec39	d72218c7b227eccaf9006be28930ec39	paris	general	28	2026-02-03 12:15:59	2026-02-03 12:15:59
1236	3	general_ai_ec87f1bd7fa01f2c58c1d1de9093f92a	ec87f1bd7fa01f2c58c1d1de9093f92a	le soudan	general	28	2026-02-03 12:16:23	2026-02-03 12:16:23
1237	3	general_ai_de9d40c29ce7c8892d1a22406f682d40	de9d40c29ce7c8892d1a22406f682d40	le dauphin	general	28	2026-02-03 12:16:56	2026-02-03 12:16:56
1238	3	general_ai_85aed27355e692f39edc9d58bb02a387	85aed27355e692f39edc9d58bb02a387	environ 390 km/h	general	28	2026-02-03 12:17:36	2026-02-03 12:17:36
1239	3	general_ai_12221b8c1b7cbdfbad4ee7b79b745888	12221b8c1b7cbdfbad4ee7b79b745888	france	general	28	2026-02-03 12:17:54	2026-02-03 12:17:54
1240	3	general_ai_c83ed4ee95ffe301acecfd1680ff7b97	c83ed4ee95ffe301acecfd1680ff7b97	henri matisse	general	28	2026-02-03 12:18:26	2026-02-03 12:18:26
1241	3	general_ai_0cf7e2cf8e06c6b8438d2a20416e7e69	0cf7e2cf8e06c6b8438d2a20416e7e69	mauna kea	general	28	2026-02-03 12:18:50	2026-02-03 12:18:50
1242	3	general_ai_35d5fc7c43b0fd0bd45a32f2e848a590	35d5fc7c43b0fd0bd45a32f2e848a590	la tortue géante	general	28	2026-02-03 12:19:28	2026-02-03 12:19:28
1243	3	general_ai_3cd4fbfd85bb30f3fe994260fe0a14ae	3cd4fbfd85bb30f3fe994260fe0a14ae	galilée	general	28	2026-02-03 12:19:49	2026-02-03 12:19:49
1244	3	general_ai_0a89332b5c1ef9731af08cc671fb08ff	0a89332b5c1ef9731af08cc671fb08ff	le río grande	general	28	2026-02-03 12:20:40	2026-02-03 12:20:40
1245	3	general_ai_753bd47e2ded97da8aa9867c4cbdb219	753bd47e2ded97da8aa9867c4cbdb219	louis xv	general	28	2026-02-03 12:21:02	2026-02-03 12:21:02
1246	3	general_ai_3764f2e439dca0930ce22bdb67726fa0	3764f2e439dca0930ce22bdb67726fa0	occitanie	general	28	2026-02-03 12:21:53	2026-02-03 12:21:53
1247	3	general_ai_a26c21af2b5bd309588cb9ab635cc94e	a26c21af2b5bd309588cb9ab635cc94e	le brésil	general	28	2026-02-03 12:22:20	2026-02-03 12:22:20
1248	3	general_ai_21e16b9d6992451af260462ffc26ca3c	21e16b9d6992451af260462ffc26ca3c	giovanni schiaparelli	general	28	2026-02-03 12:23:33	2026-02-03 12:23:33
1249	3	general_ai_ba551a0037611dca134844a1ce353558	ba551a0037611dca134844a1ce353558	constantin ier	general	28	2026-02-03 12:24:15	2026-02-03 12:24:15
\.


--
-- Data for Name: quests; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.quests (id, name, category, condition, reward_coins, rarity, badge_emoji, badge_description, detection_code, detection_params, auto_complete, created_at, updated_at) FROM stdin;
1	C'est un Départ	⚔️ Jeu	Faire une manche d'un Quiz de 10 questions	10	Standard	🚀	Cœur vert	first_match_10q	\N	t	\N	\N
2	Génie du jour	🧠 Intellectuelle	Obtenir un score parfait en mode Solo	30	Standard	💯	Trophée doré	perfect_score	\N	t	\N	\N
11	Buzz rapide	⚔️ Jeu	Être le premier à buzzer 10 fois	10	Standard	⚡	Buzzer éclatant	first_buzz_10	{"count": 10}	t	\N	\N
6	MathExpress	🧠 Intellectuelle	5 questions de calcul consécutives bien répondus	30	Standard	🧮	Symbole sigma	math_streak	{"count": 5}	t	\N	\N
5	Chicken	🧠 Intellectuelle	3 bonnes réponses sans buzzer	10	Standard	🐔	Cerveau puzzle	correct_no_buzz	{"count": 3}	t	\N	\N
9	Philosophe	🧠 Intellectuelle	Gagner un quiz de 40 questions sans skill	30	Standard	🧙	Rouleau antique	win_40q_no_skill	\N	t	\N	\N
28	Globe-trotter	🌍 Exploration	Jouer un quiz de Géographie	10	Standard	🗺️	Planète bleue	play_geography	\N	t	\N	\N
30	Archéologue	🌍 Exploration	Jouer un quiz d'Histoire	10	Standard	🏛️	Amphore dorée	play_history	\N	t	\N	\N
29	Curieux du monde	🌍 Exploration	Réussir 10 questions sur des monuments	30	Standard	🏰	Tour stylisée	monuments_10	\N	t	\N	\N
32	Citadin	🌍 Exploration	Gagner un quiz sur la Cuisine	20	Standard	🍳	Skyline	win_cuisine	\N	t	\N	\N
31	Océanographe	🌍 Exploration	Réussir 3 questions sur les océans	10	Standard	🌏	Vague bleue	oceans_3	\N	t	\N	\N
4	Encyclopédie vivante	🧠 Intellectuelle	Finir un Quiz de chaque thème	50	Standard	📖	Globe ouvert	all_themes_completed	\N	t	\N	\N
8	Polyglotte	🧠 Intellectuelle	Réussir à 60% un Quiz dans une autre langue	10	Standard	🌐	Drapeau bilingue	foreign_language_60	\N	t	\N	\N
14	Maître du temps	⚔️ Jeu	Passer une manche sans fin de chronos	50	Standard	⏰	Horloge d'or	round_no_timeout	\N	t	\N	\N
15	Buzz silencieux	⚔️ Jeu	Gagner sans son Gameplay activé	10	Standard	🔇	Buzzer muet	win_no_sound	\N	t	\N	\N
13	Résilient	⚔️ Jeu	Perdre 3 parties puis gagner la 4e	50	Standard	💪	Bouclier vert	lose_3_win_4th	\N	t	\N	\N
12	Survie	⚔️ Jeu	Finir une partie avec 1 vie restante	30	Standard	🩹	Cœur fissuré	win_with_1_life	\N	t	\N	\N
17	Noctambule	⚔️ Jeu	Jouer entre 23h et 3h du matin	15	Standard	🦉	Lune bleue	play_late_night	\N	t	\N	\N
16	Réveil matinal	⚔️ Jeu	Jouer entre 5h et 7h du matin	15	Standard	☀️	Soleil levant	play_early_morning	\N	t	\N	\N
34	Nouveau visage	🧬 Avatars	Finir 2 quiz avec avatars différents	30	Standard	🎭	Silhouette	avatars_different_2	\N	t	\N	\N
33	Touriste averti	🌍 Exploration	Réussir 1X une question piégée	50	Standard	🛡️	Globe reflet	trick_question_1	\N	t	\N	\N
26	Ligue ouverte	🧩 Sociale	Participer à un affrontement (Ligue)	50	Standard	🏆	Maillon bleu	league_participate	\N	t	\N	\N
19	Communautaire	🧩 Sociale	Rejoindre une équipe active	50	Standard	👥	Main levée	join_team	\N	t	\N	\N
18	Ami fidèle	🧩 Sociale	Inviter 1 ami via Facebook	30	Standard	🤝	Icône bleue	invite_friend	\N	t	\N	\N
24	Actif du jour	🧩 Sociale	Participer à un chat public	15	Standard	💬	Bulle de chat	chat_participate	\N	t	\N	\N
21	Curieux	🧩 Sociale	Socialiser avec 5 joueurs différents	50	Standard	👀	Icône œil	socialize_5	\N	t	\N	\N
27	Historien social	🧩 Sociale	1X Voir scores, anciens tournois	10	Standard	📊	Rouleau	view_history_1	\N	t	\N	\N
22	Partageur	🧩 Sociale	Publier un résultat sur les réseaux	50	Standard	📲	Flèche sociale	share_result	\N	t	\N	\N
25	Fête virtuelle	🧩 Sociale	1X recevez de l'aide	50	Standard	🎁	Confettis	receive_help_1	\N	t	\N	\N
20	Soutien	🧩 Sociale	1X Aider un coéquipier	10	Standard	🤲	Icône like	help_teammate_1	\N	t	\N	\N
23	Répondeur	🧩 Sociale	3X Aider un coéquipier	50	Standard	🎗️	Enveloppe	help_teammate_3	\N	t	\N	\N
35	Collectionneur sonore	🛒 Boutique & Monnaie	Acheter un son de buzzer	50	Standard	🔊	Buzzer argenté	buy_buzzer_sound	\N	t	\N	\N
36	Série de 3	⚔️ Jeu	Gagnez 3 parties consécutives	100	Rare	🔥	Flamme	win_streak_3	{"streak":3}	t	\N	\N
38	Sans Faute	🧠 Intellectuelle	Répondez correctement à 25 questions consécutives	125	Rare	🎯	Cible	correct_streak_25	{"streak":25}	t	\N	\N
39	Éclair	⚡ Vitesse	Répondez en moins d'1 seconde à 10 questions	100	Rare	⚡	Éclair	ultra_fast_answers_10	{"threshold":1,"count":10}	t	\N	\N
43	Buzzer Pro	⚡ Vitesse	Buzzez en moins de 1 seconde 20 fois	100	Rare	🔔	Cloche dorée	ultra_fast_buzz_20	{"threshold":1,"count":20}	t	\N	\N
44	Expert Stratégique	🎮 Avatar	Utilisez 50 compétences d'avatar	125	Rare	🧙‍♂️	Mage	skills_used_50	{"count":50}	t	\N	\N
48	Comeback King	⚔️ Jeu	Gagnez une partie après avoir été mené 0-5	150	Rare	👑	Couronne	comeback_0_5	{"deficit":5}	t	\N	\N
49	Domination	⚔️ Jeu	Gagnez une partie 10-0	125	Rare	⚔️	Épées croisées	perfect_10_0	\N	t	\N	\N
50	Fin de Soirée	🕐 Temporel	Jouez entre minuit et 6h du matin	75	Rare	🌙	Lune	night_owl	{"start_hour":0,"end_hour":6}	t	\N	\N
52	Série de 5	⚔️ Jeu	Gagnez 5 parties consécutives	250	Épique	🔥	Feu intense	win_streak_5	{"streak":5}	t	\N	\N
59	Invincible	⚔️ Jeu	Répondez correctement à 50 questions consécutives	400	Épique	🛡️	Bouclier	correct_streak_50	{"streak":50}	t	\N	\N
60	Boss Hunter	👹 Combat	Battez 10 boss différents en mode Solo	300	Épique	👹	Ogre japonais	boss_defeats_10	{"count":10}	t	\N	\N
62	Série de 10	⚔️ Jeu	Gagnez 10 parties consécutives	750	Légendaire	🌟	Étoile rayonnante	win_streak_10	{"streak":10}	t	\N	\N
40	Parfait x3	🧠 Intellectuelle	Obtenez 3 scores parfaits (10/10)	150	Rare	💎	Diamant	perfect_score_3	{"count":3}	t	\N	\N
41	Polyvalent	🎭 Thématique	Jouez dans 5 thèmes différents	100	Rare	🎭	Masques de théâtre	themes_5	{"themes":5}	t	\N	\N
42	Duo Élite	👥 Multijoueur	Gagnez 10 parties en mode Duo	100	Rare	👥	Silhouettes	duo_wins_10	{"wins":10}	t	\N	\N
45	Collectionneur	🎨 Collection	Déverrouillez 10 avatars différents	100	Rare	🎨	Palette	avatars_unlocked_10	{"count":10}	t	\N	\N
46	Niveau 25	📊 Progression	Atteignez le niveau 25	100	Rare	🎖️	Médaille militaire	level_25	{"level":25}	t	\N	\N
47	Richesse	💰 Économie	Accumulez 1000 pièces	100	Rare	💰	Sac d'argent	coins_1000	{"coins":1000}	t	\N	\N
51	Centurion	⚔️ Jeu	Jouez 100 parties	200	Épique	💪	Biceps	play_100_matches	{"matches":100}	t	\N	\N
53	Parfait x10	🧠 Intellectuelle	Obtenez 10 scores parfaits (10/10)	300	Épique	💠	Diamant avec point	perfect_score_10	{"count":10}	t	\N	\N
54	Encyclopédie	🎭 Thématique	Jouez dans 10 thèmes différents	250	Épique	📚	Livres	themes_10	{"themes":10}	t	\N	\N
55	Niveau 50	📊 Progression	Atteignez le niveau 50	300	Épique	🏆	Trophée	level_50	{"level":50}	t	\N	\N
56	Millionnaire	💰 Économie	Accumulez 5000 pièces	250	Épique	💎	Gemme	coins_5000	{"coins":5000}	t	\N	\N
58	Division Argent	🏅 Compétitif	Atteignez la division Argent en Duo/Ligue	250	Épique	🥈	Médaille d'argent	division_silver	\N	t	\N	\N
61	Vétéran	⚔️ Jeu	Jouez 250 parties	500	Légendaire	⭐	Étoile brillante	play_250_matches	{"matches":250}	t	\N	\N
63	Niveau 75	📊 Progression	Atteignez le niveau 75	600	Légendaire	🎖️	Médaille d'honneur	level_75	{"level":75}	t	\N	\N
64	Division Or	🏅 Compétitif	Atteignez la division Or en Duo/Ligue	750	Légendaire	🥇	Médaille d'or	division_gold	\N	t	\N	\N
37	Marathonien	⚔️ Jeu	Jouez 50 parties	75	Rare	🏃	Coureur	play_50_matches	{"matches":50}	t	\N	\N
69	Boss Hunter	👹 Combat	Battez 5 boss différents en mode Solo	125	Rare	👹	Ogre japonais	boss_defeats_5	{"count":5}	t	\N	\N
57	Maître des Avatars	🎨 Collection	Déverrouillez 25 avatars différents	300	Épique	🎭	Masques multiples	avatars_unlocked_25	{"count":25}	t	\N	\N
65	Parfait x25	🧠 Intellectuelle	Obtenez 25 scores parfaits (10/10)	1000	Légendaire	💠	Diamant parfait	perfect_score_25	{"count":25}	t	\N	\N
66	Maître Absolu	⚔️ Jeu	Jouez 500 parties	2000	Maître	👑	Couronne royale	play_500_matches	{"matches":500}	t	\N	\N
67	Niveau 100	📊 Progression	Atteignez le niveau maximum 100	3000	Maître	💯	Cent points	level_100	{"level":100}	t	\N	\N
68	Division Légende	🏅 Compétitif	Atteignez la division Légende en Duo/Ligue	1500	Maître	🌠	Étoile filante	division_legend	\N	t	\N	\N
70	Œil Vif	Performance	Répondre correctement à 10 questions en moins de 2 secondes	50	Standard	👁️	Œil Vif - Répondre rapidement à 10 questions	fast_answers_10	{"count":10,"time":2}	f	2025-10-26 04:51:40	2025-10-26 04:51:40
71	Réflexion éclair	Performance	Buzzer en moins de 1 seconde 10 fois	50	Standard	⚡	Réflexion éclair - Buzzer ultra-rapide 10 fois	buzz_fast_10	{"count":10,"time":1}	f	2025-10-26 04:51:40	2025-10-26 04:51:40
72	Liseur de l'ombre	Stratégie	Utiliser une compétence d'avatar stratégique	50	Standard	🔮	Liseur de l'ombre - Utiliser une compétence stratégique	skill_used	{"count":1}	f	2025-10-26 04:51:40	2025-10-26 04:51:40
76	Réveil du génie	Intellectuelle	Gagné une manche sans buzzer 5X	50	Quotidiennes	☀️	Ampoule matinale	daily_wins_no_buzz	{"count": 5}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
77	Coup de buzz	Jeu	Être le premier à buzzer au moins 3 fois dans la journée	55	Quotidiennes	🔔	Buzzer rapide	daily_first_buzz_3	{"count": 3}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
78	Ami du jour	Sociale	Envoyer une invitation à un joueur	50	Quotidiennes	🤝	Icône main tendue	daily_invite_player	{"count": 1}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
79	Quiz éclair	Jeu	Finir un quiz de 10 questions sans erreur	70	Quotidiennes	⚡	Éclair jaune	daily_perfect_10	{"count": 1}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
80	Exploration express	Exploration	Jouer un quiz d'un thème différent du jour précédent	55	Quotidiennes	🌎	Globe bleu	daily_different_theme	{"count": 1}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
81	Avatar du matin	Avatars	Changer d'avatar avant ta première partie	50	Quotidiennes	👤	Silhouette mobile	daily_change_avatar	{"count": 1}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
82	Buzz du soir	Jeu	Finir une partie entre 19h et 23h	50	Quotidiennes	🌙	Demi-lune	daily_evening_play	{"count": 1}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
83	Apprenti du jour	Maîtrise du Jeu	Créer une question personnalisée avec l'IA	70	Quotidiennes	🤖	Plume IA	daily_create_ai_question	{"count": 1}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
84	Partage matinal	Sociale	Publier un résultat sur les réseaux	50	Quotidiennes	📤	Icône sociale	daily_share_result	{"count": 1}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
85	Collectionneur actif	Boutique & Monnaie	Consulter la boutique	25	Quotidiennes	🛒	Panier bleu	daily_visit_shop	{"count": 1}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
86	Curieux constant	Intellectuelle	Lire la description d'un Avatar Stratégique	25	Quotidiennes	📚	Livre ouvert	daily_read_avatar_desc	{"count": 1}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
87	Duel du jour	Jeu	Gagner une partie Duo	75	Quotidiennes	🤜🤛	Icône duel	daily_win_duo	{"count": 1}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
88	Maître en herbe	Maîtrise du Jeu	Finissez une partie personnalisée 4+ joueurs	75	Quotidiennes	🎛️	Icône console	daily_finish_custom_4plus	{"count": 1}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
89	Réactif	Intellectuelle	sélectionné une réponse en moins d'1,5 sec	50	Quotidiennes	⏱️	Chrono vert	daily_answer_fast_1_5sec	{"count": 1}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
90	Fidèle du jour	Sociale	Une partie en Solo dans Ligue	75	Quotidiennes	📅	Soleil	daily_league_solo	{"count": 1}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
91	Petit investisseur	Boutique & Monnaie	Acheter un objet dans la boutique	200	Quotidiennes	💰	Pièce dorée	daily_buy_item	{"count": 1}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
92	Découvreur du jour	Exploration	Jouer 5 quiz "Général"	75	Quotidiennes	🔬	Icône microscope	daily_play_general_5	{"count": 5}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
93	Stratégie éclair	Avatars	Utiliser un skill d'avatar stratégique	50	Quotidiennes	🎯	Icône pouvoir	daily_use_skill	{"count": 1}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
94	Coach social	Sociale	Aider un joueur dans le besoin vie ou pièces	100	Quotidiennes	🧑‍🏫	Étoile bleue	daily_help_player	{"count": 1}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
95	Focus ultime	Intellectuelle	Terminer 5 Parties en 2 manche	75	Quotidiennes	👁️	Œil concentré	daily_finish_5_fast	{"count": 5}	f	2025-10-26 22:58:01	2025-10-26 22:58:01
96	Série d'excellence	Série	Gagner 3 manches consécutives	80	Rare	🔥	Flamme triple	consecutive_wins	{"count":3}	f	\N	\N
97	Roi du temps	Performance	Répondre à 5 questions en moins de 2 secondes chacune	85	Rare	⏱️	Chronomètre précis	fast_answers	{"count":5,"max_time":2}	f	\N	\N
98	Touche-à-tout	Exploration	Jouer dans 5 thèmes différents	75	Rare	🌈	Arc-en-ciel	different_themes	{"count":5}	f	\N	\N
99	Machine à réponses	Performance	Répondre correctement à 20 questions d'affilée	90	Rare	🤖	Robot intelligent	consecutive_correct	{"count":20}	f	\N	\N
100	Champion du duo	Duo	Gagner 5 matchs en mode Duo	85	Rare	👥	Duo victorieux	duo_wins	{"count":5}	f	\N	\N
101	Expert thématique	Thème	Obtenir 10 victoires dans un même thème	80	Rare	📚	Livre ouvert	theme_wins	{"count":10,"same_theme":true}	f	\N	\N
102	Invincible	Série	Jouer 10 matchs sans perdre	95	Rare	🛡️	Bouclier protecteur	undefeated_streak	{"count":10}	f	\N	\N
103	Précision mortelle	Performance	Atteindre 95% de précision sur 20 questions	85	Rare	🎯	Cible précise	accuracy_rate	{"count":20,"accuracy":95}	f	\N	\N
104	Marathonien	Endurance	Jouer 3 heures en une journée	90	Rare	🏃	Coureur endurant	daily_playtime	{"hours":3}	f	\N	\N
105	Gentleman du buzzer	Fair-play	Ne jamais buzzer incorrectement sur 10 matchs	80	Rare	🎩	Chapeau élégant	perfect_buzz_accuracy	{"matches":10}	f	\N	\N
106	Collectionneur d'avatars	Collection	Déverrouiller 10 avatars différents	75	Rare	🎭	Masques variés	avatar_collection	{"count":10}	f	\N	\N
107	Maître de la stratégie	Stratégie	Utiliser une compétence d'avatar 20 fois	85	Rare	🧠	Cerveau stratégique	skill_usage	{"count":20}	f	\N	\N
108	Ascension rapide	Progression	Monter de 5 niveaux en une semaine	90	Rare	📈	Graphique montant	weekly_level_gain	{"levels":5}	f	\N	\N
109	Commerçant avisé	Boutique	Acheter 5 objets dans la boutique	70	Rare	🛒	Caddie rempli	shop_purchases	{"count":5}	f	\N	\N
110	Combattant de boss	Boss	Vaincre un boss	100	Rare	⚔️	Épées croisées	boss_defeat	{"count":1}	f	\N	\N
111	Nocturne	Temporel	Jouer 5 matchs entre minuit et 6h du matin	75	Rare	🌙	Lune croissant	night_matches	{"count":5,"start_hour":0,"end_hour":6}	f	\N	\N
112	Spécialiste du thème	Thème	Jouer 50 matchs dans un seul thème	85	Rare	🎓	Chapeau diplômé	theme_dedication	{"matches":50,"same_theme":true}	f	\N	\N
113	Comeback king	Résilience	Gagner un match après avoir perdu les 2 premières manches	95	Rare	👑	Couronne royale	comeback_victory	{"rounds_lost_first":2}	f	\N	\N
114	Ambassadeur	Sociale	Inviter 5 nouveaux joueurs	100	Rare	📢	Mégaphone	referrals	{"count":5}	f	\N	\N
140	Perfectionniste ultime	Performance	Obtenir 100% de réponses correctes sur 50 questions	150	Épique	💯	Score parfait	perfect_accuracy_epique	{"count":50,"accuracy":100}	f	\N	\N
141	Héros du buzzer	Buzz	Être le premier à buzzer 50 fois dans la journée	140	Épique	🦸	Super-héros	daily_first_buzz_50	{"count":50}	f	\N	\N
142	Encyclopédie vivante	Connaissance	Jouer dans tous les thèmes disponibles	160	Épique	📖	Livre encyclopédie	all_themes_played	{"all_themes":true}	f	\N	\N
143	Légende du duo	Duo	Atteindre le rang Diamant en mode Duo	170	Épique	💎	Diamant brillant	duo_rank_diamond	{"rank":"Diamant"}	f	\N	\N
144	Boss slayer	Boss	Vaincre 5 boss différents	180	Épique	🏆	Trophée champion	boss_defeats	{"count":5,"unique":true}	f	\N	\N
145	Marathon nocturne	Endurance	Jouer 50 matchs entre 22h et 6h	145	Épique	🦉	Hibou noctambule	night_marathon	{"count":50,"start_hour":22,"end_hour":6}	f	\N	\N
146	Collectionneur fou	Collection	Déverrouiller 30 avatars différents	155	Épique	🎨	Palette artistique	avatar_collection_epique	{"count":30}	f	\N	\N
147	Speed demon	Vitesse	Répondre à 20 questions en moins d'1 seconde chacune	165	Épique	⚡	Éclair rapide	ultra_fast_answers_epique	{"count":20,"max_time":1}	f	\N	\N
148	Indestructible	Série	Gagner 25 matchs d'affilée	175	Épique	🔱	Trident puissant	win_streak_epique	{"count":25}	f	\N	\N
149	Magnat de la boutique	Boutique	Acheter 20 objets dans la boutique	150	Épique	💰	Sac d'argent	shop_purchases_epique	{"count":20}	f	\N	\N
150	Génie stratégique	Stratégie	Utiliser 10 compétences d'avatar différentes	160	Épique	🎯	Cible stratégique	unique_skills_used	{"count":10,"unique":true}	f	\N	\N
151	Ascension fulgurante	Progression	Atteindre le niveau 50	170	Épique	🚀	Fusée décollage	level_reached_epique	{"level":50}	f	\N	\N
152	Champion international	Ligue	Gagner 50 matchs en mode Ligue	165	Épique	🌍	Globe terrestre	league_wins	{"count":50}	f	\N	\N
153	Maître des thèmes	Thème	Obtenir 50 victoires dans 5 thèmes différents	180	Épique	🎪	Chapiteau cirque	multi_theme_mastery	{"wins_per_theme":50,"themes":5}	f	\N	\N
154	Influenceur StrategyBuzzer	Sociale	Inviter 20 nouveaux joueurs	190	Épique	📱	Téléphone social	referrals_epique	{"count":20}	f	\N	\N
155	Millionnaire	Richesse	Accumuler 1 000 000 coins	200	Épique	💵	Billet de banque	coins_accumulated	{"amount":1000000}	f	\N	\N
156	Vétéran aguerri	Expérience	Jouer 500 matchs au total	175	Épique	⚓	Ancre marine	total_matches_epique	{"count":500}	f	\N	\N
157	Roi du comeback	Résilience	Gagner 10 matchs après avoir perdu les 2 premières manches	185	Épique	🔄	Flèches circulaires	comeback_victories_epique	{"count":10,"rounds_lost_first":2}	f	\N	\N
158	Maître du jeu ultime	Master	Organiser 50 quiz en mode Maître du jeu	170	Épique	🎬	Clap de cinéma	master_games_hosted_epique	{"count":50}	f	\N	\N
159	Conquérant de divisions	Progression	Atteindre la division Légende	195	Épique	👑	Couronne légende	division_reached	{"division":"L\\u00e9gende"}	f	\N	\N
160	Dieu du buzzer	Buzz	Être le premier à buzzer 500 fois	300	Légendaire	⚡	Éclair divin	first_buzz_total	{"count":500}	f	\N	\N
161	Immortel	Série	Gagner 100 matchs d'affilée	350	Légendaire	♾️	Infini éternel	win_streak_legendaire	{"count":100}	f	\N	\N
162	Exterminateur de boss	Boss	Vaincre tous les boss du jeu	400	Légendaire	🗡️	Épée légendaire	all_bosses_defeated	{"all_bosses":true}	f	\N	\N
163	Perfectionniste absolu	Performance	Obtenir 100% sur 500 questions	380	Légendaire	🌟	Étoile brillante	perfect_accuracy_legendaire	{"count":500,"accuracy":100}	f	\N	\N
164	Empereur de StrategyBuzzer	Progression	Atteindre le niveau 100	500	Légendaire	👑	Couronne impériale	level_reached_legendaire	{"level":100}	f	\N	\N
165	Collectionneur ultime	Collection	Déverrouiller tous les avatars du jeu	450	Légendaire	🎁	Cadeau complet	all_avatars_unlocked	{"all_avatars":true}	f	\N	\N
166	Milliardaire	Richesse	Accumuler 10 000 000 coins	600	Légendaire	💎	Diamant fortune	coins_accumulated_legendaire	{"amount":10000000}	f	\N	\N
167	Champion du monde	Ligue	Atteindre le top 10 mondial en Ligue	550	Légendaire	🥇	Médaille d'or	league_ranking	{"rank":10}	f	\N	\N
168	Vitesse lumière	Vitesse	Répondre à 100 questions en moins de 0.5 seconde	320	Légendaire	💫	Étoile filante	ultra_fast_answers_legendaire	{"count":100,"max_time":0.5}	f	\N	\N
169	Légende vivante	Expérience	Jouer 5000 matchs	480	Légendaire	🏛️	Temple antique	total_matches_legendaire	{"count":5000}	f	\N	\N
170	Maître omniscient	Connaissance	Atteindre 100% de victoires dans tous les thèmes	520	Légendaire	🔮	Boule cristal	all_themes_mastery	{"all_themes":true,"win_rate":100}	f	\N	\N
171	Grand maître des quiz	Master	Organiser 500 quiz en mode Maître du jeu	420	Légendaire	🎭	Masque maître	master_games_hosted_legendaire	{"count":500}	f	\N	\N
172	Influenceur légendaire	Sociale	Inviter 100 nouveaux joueurs	580	Légendaire	📡	Antenne signal	referrals_legendaire	{"count":100}	f	\N	\N
173	Stratège suprême	Stratégie	Utiliser toutes les compétences d'avatar du jeu	460	Légendaire	🧩	Pièce puzzle complète	all_skills_used	{"all_skills":true}	f	\N	\N
174	Phoenix éternel	Résilience	Gagner 50 matchs après avoir perdu les 2 premières manches	440	Légendaire	🔥	Phoenix flammes	comeback_victories_legendaire	{"count":50,"rounds_lost_first":2}	f	\N	\N
175	Gardien du temps	Temporel	Jouer au moins 1 match chaque jour pendant 365 jours	650	Légendaire	⏳	Sablier temps	daily_streak	{"days":365}	f	\N	\N
176	Dieu vivant de StrategyBuzzer	Ultime	Compléter toutes les quêtes du jeu	10000	Maître	🌌	Galaxie univers	all_quests_completed	{"all_quests":true}	f	\N	\N
177	Maître de l'univers StrategyBuzzer	Ultime	Atteindre le rang #1 mondial	15000	Maître	🏅	Médaille suprême	world_rank_1	{"rank":1}	f	\N	\N
178	Éternel champion	Ultime	Maintenir 100% de taux de victoire sur 1000 matchs	12000	Maître	💠	Diamant parfait	perfect_win_rate	{"matches":1000,"win_rate":100}	f	\N	\N
179	Architecte de légendes	Ultime	Organiser 1000 quiz en mode Maître du jeu	11000	Maître	🎯	Cible absolue	master_games_hosted_maitre	{"count":1000}	f	\N	\N
180	Créateur d'empire	Ultime	Inviter 500 nouveaux joueurs	20000	Maître	🌟	Étoile suprême	referrals_maitre	{"count":500}	f	\N	\N
181	Virtuose des thèmes	Thème	Gagner 100 matchs répartis sur 10 thèmes différents	95	Rare	🎨	Palette multicolore	multi_theme_wins_rare	{"wins":100,"themes":10}	f	\N	\N
182	Seigneur du buzzer	Buzz	Être le premier à buzzer 1000 fois	420	Légendaire	👑	Couronne buzzer	first_buzz_total_legendaire	{"count":1000}	f	\N	\N
183	Titan indomptable	Série	Gagner 200 matchs d'affilée	700	Légendaire	⚡	Foudre titan	win_streak_titan	{"count":200}	f	\N	\N
\.


--
-- Data for Name: team_invitations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.team_invitations (id, team_id, user_id, invited_by, status, expires_at, created_at, updated_at) FROM stdin;
1	1	8	3	accepted	2025-12-15 19:28:17	2025-12-08 19:28:17	2025-12-08 19:28:35
2	1	8	3	accepted	2025-12-15 20:24:09	2025-12-08 20:24:09	2025-12-08 20:27:09
3	1	5	3	accepted	2025-12-15 20:31:48	2025-12-08 20:31:48	2025-12-08 20:32:43
4	1	7	3	accepted	2025-12-15 20:35:13	2025-12-08 20:35:13	2025-12-08 20:35:25
5	1	4	3	accepted	2025-12-15 20:38:25	2025-12-08 20:38:25	2025-12-08 20:38:58
6	1	3	4	accepted	2025-12-15 21:23:55	2025-12-08 21:23:55	2025-12-08 21:24:17
7	1	8	3	pending	2025-12-27 08:28:33	2025-12-20 08:28:33	2025-12-20 08:28:33
\.


--
-- Data for Name: team_join_requests; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.team_join_requests (id, team_id, user_id, message, status, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: team_members; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.team_members (id, team_id, user_id, role, joined_at, created_at, updated_at) FROM stdin;
4	1	5	member	2025-12-08 20:32:43	2025-12-08 20:32:43	2025-12-08 20:32:43
5	1	7	member	2025-12-08 20:35:24	2025-12-08 20:35:24	2025-12-08 20:35:24
7	1	3	captain	2025-12-08 21:24:17	2025-12-08 21:24:17	2025-12-08 21:24:17
6	1	4	member	2025-12-08 20:38:58	2025-12-08 20:38:58	2025-12-08 20:44:17
\.


--
-- Data for Name: teams; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.teams (id, name, tag, captain_id, division, points, level, matches_played, matches_won, matches_lost, is_recruiting, created_at, updated_at, emblem_category, emblem_index, custom_emblem_path, team_code) FROM stdin;
1	Québ	QUé	3	bronze	0	1	0	0	0	t	2025-12-08 16:08:07	2025-12-20 08:26:53	royalty	15	\N	EQ-00001
\.


--
-- Data for Name: user_avatars; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.user_avatars (id, user_id, avatar_name, unlocked, created_at, updated_at) FROM stdin;
1	4	mathematicien	t	2026-01-18 13:51:28	2026-01-18 13:51:28
2	4	scientifique	t	2026-01-18 13:51:28	2026-01-18 13:51:28
3	4	explorateur	t	2026-01-18 13:51:28	2026-01-18 13:51:28
4	4	defenseur	t	2026-01-18 13:51:28	2026-01-18 13:51:28
5	4	comedienne	t	2026-01-18 13:51:28	2026-01-18 13:51:28
6	4	magicienne	t	2026-01-18 13:51:28	2026-01-18 13:51:28
7	4	challenger	t	2026-01-18 13:51:28	2026-01-18 13:51:28
8	4	historien	t	2026-01-18 13:51:28	2026-01-18 13:51:28
9	4	ia-junior	t	2026-01-18 13:51:28	2026-01-18 13:51:28
10	4	stratege	t	2026-01-18 13:51:28	2026-01-18 13:51:28
11	4	sprinteur	t	2026-01-18 13:51:28	2026-01-18 13:51:28
12	4	visionnaire	t	2026-01-18 13:51:28	2026-01-18 13:51:28
13	7	mathematicien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
14	5	mathematicien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
15	2	mathematicien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
16	6	mathematicien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
17	8	mathematicien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
18	1	mathematicien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
19	9	mathematicien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
20	10	mathematicien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
21	3	mathematicien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
22	7	scientifique	t	2026-01-18 22:50:33	2026-01-18 22:50:33
23	5	scientifique	t	2026-01-18 22:50:33	2026-01-18 22:50:33
24	2	scientifique	t	2026-01-18 22:50:33	2026-01-18 22:50:33
25	6	scientifique	t	2026-01-18 22:50:33	2026-01-18 22:50:33
26	8	scientifique	t	2026-01-18 22:50:33	2026-01-18 22:50:33
27	1	scientifique	t	2026-01-18 22:50:33	2026-01-18 22:50:33
28	9	scientifique	t	2026-01-18 22:50:33	2026-01-18 22:50:33
29	10	scientifique	t	2026-01-18 22:50:33	2026-01-18 22:50:33
30	3	scientifique	t	2026-01-18 22:50:33	2026-01-18 22:50:33
31	7	explorateur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
32	5	explorateur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
33	2	explorateur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
34	6	explorateur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
35	8	explorateur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
36	1	explorateur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
37	9	explorateur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
38	10	explorateur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
39	3	explorateur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
40	7	defenseur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
41	5	defenseur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
42	2	defenseur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
43	6	defenseur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
44	8	defenseur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
45	1	defenseur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
46	9	defenseur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
47	10	defenseur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
48	3	defenseur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
49	7	comedienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
50	5	comedienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
51	2	comedienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
52	6	comedienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
53	8	comedienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
54	1	comedienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
55	9	comedienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
56	10	comedienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
57	3	comedienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
58	7	magicienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
59	5	magicienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
60	2	magicienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
61	6	magicienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
62	8	magicienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
63	1	magicienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
64	9	magicienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
65	10	magicienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
66	3	magicienne	t	2026-01-18 22:50:33	2026-01-18 22:50:33
67	7	challenger	t	2026-01-18 22:50:33	2026-01-18 22:50:33
68	5	challenger	t	2026-01-18 22:50:33	2026-01-18 22:50:33
69	2	challenger	t	2026-01-18 22:50:33	2026-01-18 22:50:33
70	6	challenger	t	2026-01-18 22:50:33	2026-01-18 22:50:33
71	8	challenger	t	2026-01-18 22:50:33	2026-01-18 22:50:33
72	1	challenger	t	2026-01-18 22:50:33	2026-01-18 22:50:33
73	9	challenger	t	2026-01-18 22:50:33	2026-01-18 22:50:33
74	10	challenger	t	2026-01-18 22:50:33	2026-01-18 22:50:33
75	3	challenger	t	2026-01-18 22:50:33	2026-01-18 22:50:33
76	7	historien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
77	5	historien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
78	2	historien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
79	6	historien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
80	8	historien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
81	1	historien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
82	9	historien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
83	10	historien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
84	3	historien	t	2026-01-18 22:50:33	2026-01-18 22:50:33
85	7	ia-junior	t	2026-01-18 22:50:33	2026-01-18 22:50:33
86	5	ia-junior	t	2026-01-18 22:50:33	2026-01-18 22:50:33
87	2	ia-junior	t	2026-01-18 22:50:33	2026-01-18 22:50:33
88	6	ia-junior	t	2026-01-18 22:50:33	2026-01-18 22:50:33
89	8	ia-junior	t	2026-01-18 22:50:33	2026-01-18 22:50:33
90	1	ia-junior	t	2026-01-18 22:50:33	2026-01-18 22:50:33
91	9	ia-junior	t	2026-01-18 22:50:33	2026-01-18 22:50:33
92	10	ia-junior	t	2026-01-18 22:50:33	2026-01-18 22:50:33
93	3	ia-junior	t	2026-01-18 22:50:33	2026-01-18 22:50:33
94	7	stratege	t	2026-01-18 22:50:33	2026-01-18 22:50:33
95	5	stratege	t	2026-01-18 22:50:33	2026-01-18 22:50:33
96	2	stratege	t	2026-01-18 22:50:33	2026-01-18 22:50:33
97	6	stratege	t	2026-01-18 22:50:33	2026-01-18 22:50:33
98	8	stratege	t	2026-01-18 22:50:33	2026-01-18 22:50:33
99	1	stratege	t	2026-01-18 22:50:33	2026-01-18 22:50:33
100	9	stratege	t	2026-01-18 22:50:33	2026-01-18 22:50:33
101	10	stratege	t	2026-01-18 22:50:33	2026-01-18 22:50:33
102	3	stratege	t	2026-01-18 22:50:33	2026-01-18 22:50:33
103	7	sprinteur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
104	5	sprinteur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
105	2	sprinteur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
106	6	sprinteur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
107	8	sprinteur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
108	1	sprinteur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
109	9	sprinteur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
110	10	sprinteur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
111	3	sprinteur	t	2026-01-18 22:50:33	2026-01-18 22:50:33
112	7	visionnaire	t	2026-01-18 22:50:33	2026-01-18 22:50:33
113	5	visionnaire	t	2026-01-18 22:50:33	2026-01-18 22:50:33
114	2	visionnaire	t	2026-01-18 22:50:33	2026-01-18 22:50:33
115	6	visionnaire	t	2026-01-18 22:50:33	2026-01-18 22:50:33
116	8	visionnaire	t	2026-01-18 22:50:33	2026-01-18 22:50:33
117	1	visionnaire	t	2026-01-18 22:50:33	2026-01-18 22:50:33
118	9	visionnaire	t	2026-01-18 22:50:33	2026-01-18 22:50:33
119	10	visionnaire	t	2026-01-18 22:50:33	2026-01-18 22:50:33
120	3	visionnaire	t	2026-01-18 22:50:33	2026-01-18 22:50:33
\.


--
-- Data for Name: user_preferences; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.user_preferences (id, user_id, avatar_joueur, avatar_strategique, strategie_preferee, langue, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: user_quest_progress; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.user_quest_progress (id, user_id, quest_id, completed_at, progress, rewarded, created_at, updated_at) FROM stdin;
1	1	47	2025-10-26 03:38:39	[]	t	2025-10-26 03:38:39	2025-10-26 03:38:39
2	1	56	2025-10-26 03:38:41	[]	t	2025-10-26 03:38:40	2025-10-26 03:38:41
3	2	47	2025-10-26 03:38:54	[]	t	2025-10-26 03:38:53	2025-10-26 03:38:54
4	3	1	2025-10-26 05:11:57	[]	t	2025-10-26 05:11:57	2025-10-26 05:11:57
5	3	2	2025-10-26 05:11:58	[]	t	2025-10-26 05:11:58	2025-10-26 05:11:58
6	3	40	2025-10-26 05:12:07	[]	t	2025-10-26 05:12:07	2025-10-26 05:12:07
7	3	41	2025-10-26 05:12:07	[]	t	2025-10-26 05:12:07	2025-10-26 05:12:07
8	3	42	2025-10-26 05:12:08	[]	t	2025-10-26 05:12:08	2025-10-26 05:12:08
9	3	47	2025-10-26 05:12:09	[]	t	2025-10-26 05:12:09	2025-10-26 05:12:09
10	3	53	2025-10-26 05:12:10	[]	t	2025-10-26 05:12:10	2025-10-26 05:12:10
11	3	37	2025-10-26 05:12:12	[]	t	2025-10-26 05:12:12	2025-10-26 05:12:12
12	5	1	\N	\N	f	2025-10-27 00:17:02	2025-10-27 00:17:02
13	5	97	\N	\N	f	2025-10-27 00:17:12	2025-10-27 00:17:12
18	10	11	2025-12-19 22:21:04	{"first_buzzes":10}	t	2025-12-19 22:15:59	2025-12-19 22:21:04
19	10	1	2025-12-19 22:24:37	[]	t	2025-12-19 22:24:36	2025-12-19 22:24:37
14	8	11	2025-11-17 15:13:23	{"first_buzzes":10}	t	2025-11-17 15:06:03	2025-11-17 15:13:23
20	4	11	2025-12-30 01:33:55	{"first_buzzes":10}	t	2025-12-29 23:17:43	2025-12-30 01:33:55
21	4	1	2026-01-03 21:48:38	[]	t	2026-01-03 21:48:38	2026-01-03 21:48:38
15	3	11	2025-11-17 18:33:33	{"first_buzzes":10}	t	2025-11-17 16:08:21	2025-11-17 18:33:33
16	6	11	2025-11-18 12:07:14	{"first_buzzes":10}	t	2025-11-18 11:42:01	2025-11-18 12:07:14
17	6	1	2025-11-18 12:10:39	[]	t	2025-11-18 12:10:38	2025-11-18 12:10:39
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.users (id, name, email, email_verified_at, password, coins, lives, infinite_lives_until, rank, profile_settings, remember_token, created_at, updated_at, intelligence_pieces, profile_completed, master_purchased, player_code, next_life_regen, ambient_music_id, ambient_music_enabled, gameplay_music_id, gameplay_music_enabled, preferred_language, duo_purchased, league_purchased, temp_access_division, temp_access_expires_at, current_match_id, match_started_at, competence_coins) FROM stdin;
4	Emi D	emidesrosierss@gmail.com	\N	$2y$12$aJy2RXpvnx721y1imLnSZu/pN.BoOAklejsufpTd25Q1LoGh8WJ9K	30	3	\N	Rookie	{"pseudonym":"Emi D","avatar":{"type":"regular","id":"1.png","name":null,"url":"images\\/avatars\\/portraits\\/1.png"},"strategic_avatar":{"id":null,"name":null,"url":null},"show_in_league":"Oui","show_online":false,"language":"fr","country":"CA","sound":{"ambiance":false,"buzzer":true,"results":true,"buzzer_id":"buzzer_default_1","music_id":null},"gameplay":{"music_id":"strategybuzzer"},"theme":{"style":"Classique","decor":null},"unlocked_avatars":["portraits","explorateur"],"gm":{"xp":60,"total_victories":1,"last_victory_date":"2026-01-03 21:48:37"},"choix_niveau":2}	\N	2025-10-13 17:49:04	2026-01-03 21:48:40	0	t	t	SB-15K4	\N	strategybuzzer	t	\N	t	fr	f	t	\N	\N	\N	\N	221
7	SteveLe007	stevele007@hotmail.com	\N	$2y$12$ahIlJafF.qIHxZFNwWt6Lu3lfRfaofZdqOHV8fUuPvhFr73Ba8tCK	0	3	\N	Rookie	{"sound": {"buzzer": true, "results": true, "ambiance": false, "music_id": "strategybuzzer", "buzzer_id": "buzzer_default_2"}, "theme": {"decor": null, "style": "Classique"}, "avatar": {"id": "standard2.png", "url": "images/avatars/standard/standard2.png", "name": null, "type": "regular"}, "country": "CA", "gameplay": {"music_id": "strategybuzzer"}, "language": "fr", "pseudonym": "SteveLe007", "show_online": false, "show_in_league": "Oui", "strategic_avatar": {"id": null, "url": null, "name": null}, "unlocked_avatars": ["animal", "portraits", "mathematicien", "scientifique", "explorateur", "defenseur", "comedienne", "magicienne", "challenger", "historien", "ia-junior", "stratege", "sprinteur", "visionnaire"]}	\N	2025-10-16 00:06:10	2025-12-08 19:53:19	0	t	f	SB-LXCZ	\N	strategybuzzer	t	\N	t	fr	f	t	\N	\N	\N	\N	400
2	Test Regis	test.register@example.com	\N	$2y$12$nybepxJlIwBg4SYa62rnyeygPyFwB4EvYoaHcO2iUoqDp1mUWf/HW	0	3	\N	Rookie	\N	\N	2025-10-07 18:35:42	2025-11-17 15:56:07	0	f	t	SB-QXHJ	\N	strategybuzzer	t	\N	t	fr	f	t	\N	\N	\N	\N	1100
9	L Dufour	lucdufour@hotmail.com	\N	$2y$12$TVnsHLuzNHNBDjLieH2/1e8OW3G1wGGpPr3zifi3uCNm8FF4qRORq	0	3	\N	Rookie	{"pseudonym":"L Dufour","avatar":{"type":"regular","id":"standard1.png","name":null,"url":"images\\/avatars\\/standard\\/standard1.png"},"strategic_avatar":{"id":null,"name":null,"url":null},"show_in_league":"Oui","show_online":false,"language":"fr","country":"CA","sound":{"ambiance":false,"buzzer":true,"results":true,"buzzer_id":"buzzer_default_2","music_id":"strategybuzzer"},"gameplay":{"music_id":"strategybuzzer"},"theme":{"style":"Classique","decor":null}}	\N	2025-12-17 05:27:42	2025-12-17 05:36:43	0	t	f	SB-XWWD	\N	strategybuzzer	t	\N	t	fr	f	f	\N	\N	\N	\N	1000
10	Kim B	kimberlybouvier68@gmail.com	\N	$2y$12$kONXzJxtR.LWuvzWRFXWuO4SV9oRJuA0/keTSzjbCNaasC8rKlZ8m	0	3	\N	Rookie	{"pseudonym":"Kim B","avatar":{"type":"regular","id":"standard8.png","name":null,"url":"images\\/avatars\\/standard\\/standard8.png"},"strategic_avatar":{"id":null,"name":null,"url":null},"show_in_league":"Oui","show_online":false,"language":"fr","country":"CA","sound":{"ambiance":false,"buzzer":true,"results":true,"buzzer_id":"buzzer_default_1","music_id":"strategybuzzer"},"gameplay":{"music_id":"strategybuzzer"},"theme":{"style":"Classique","decor":null},"gm":{"xp":60,"total_victories":1,"last_victory_date":"2025-12-19 22:24:36"},"choix_niveau":2}	\N	2025-12-19 21:46:32	2025-12-19 22:28:04	0	t	f	SB-7NHY	\N	strategybuzzer	t	\N	t	fr	f	f	\N	\N	\N	\N	1021
3	LeFunny	lefunnygr@gmail.com	\N	$2y$12$nKY9H8UOhg5OZOrtanwFLuISejhOBHDbZ/mo3HwE83p/Rg5mQ4ZGy	30	3	\N	Rookie	{"pseudonym":"LeFunny","avatar":{"type":"regular","id":"2.png","name":null,"url":"images\\/avatars\\/portraits\\/2.png"},"strategic_avatar":{"id":"stratege","name":"Strat\\u00e8ge","url":"images\\/avatars\\/stratege.png"},"show_in_league":"Oui","show_online":false,"language":"fr","country":"CA","sound":{"ambiance":false,"buzzer":true,"results":true,"buzzer_id":"buzzer_default_1","music_id":"strategybuzzer"},"gameplay":{"music_id":"strategybuzzer"},"theme":{"style":"Classique","decor":null},"gm":{"xp":13950,"solo_level":100,"total_defeats":14,"total_victories":17,"last_defeat_date":"2026-02-03 12:22:54","last_victory_date":"2026-01-26 14:51:41"},"choix_niveau":100,"unlocked_avatars":["portraits","premium-tich_tich_tichou","mathematicien","scientifique","explorateur","defenseur","comedienne","magicienne","challenger","historien","ia-junior","stratege","sprinteur","visionnaire"],"daily_quests_rotation":{"reset_at":"2025-11-27 19:43:16","quest_ids":[76,87,91]},"active_strategic_avatar":"scientifique"}	\N	2025-10-07 18:54:10	2026-02-04 13:11:10	0	t	t	SB-TEQN	\N	strategybuzzer	t	\N	t	fr	f	t	\N	\N	\N	\N	500
5	Lefebvre M	leftmarie@hotmail.com	\N	$2y$12$5WOrlkwv8s8257tmUt/imOsmCyEQorei77P4MmB2z2Z94PODARWxO	0	3	\N	Rookie	{"pseudonym":"Lefebvre M","avatar":{"type":"regular","id":"standard8.png","name":null,"url":"images\\/avatars\\/standard\\/standard8.png"},"strategic_avatar":{"id":null,"name":null,"url":null},"show_in_league":"Oui","show_online":false,"language":"fr","country":"CA","sound":{"ambiance":true,"buzzer":true,"results":true,"buzzer_id":"buzzer_default_1","music_id":null},"gameplay":{"music_id":"strategybuzzer"},"theme":{"style":"Classique","decor":null}}	\N	2025-10-15 12:18:45	2025-12-08 19:58:06	0	t	f	SB-LRGT	\N	strategybuzzer	t	\N	t	fr	f	t	\N	\N	\N	\N	1000
6	Roumanis D	dinaroumanis@gmail.com	\N	$2y$12$SYuS2yfckr3KiIGWfNvk/uKNmzWlI96QLRSswRsHZSXeXKwaB01wq	0	3	\N	Rookie	{"pseudonym":"Roumanis D","avatar":{"type":"regular","id":"standard4.png","name":null,"url":"images\\/avatars\\/standard\\/standard4.png"},"strategic_avatar":{"id":"challenger","name":"Challenger","url":"images\\/avatars\\/challenger.png"},"show_in_league":"Oui","show_online":false,"language":"en","country":"CA","sound":{"ambiance":false,"buzzer":true,"results":true,"buzzer_id":"buzzer_default_2","music_id":null},"gameplay":{"music_id":"strategybuzzer"},"theme":{"style":"Classique","decor":null},"gm":{"xp":60,"total_victories":1,"last_victory_date":"2025-11-18 12:10:38"},"choix_niveau":2,"unlocked_avatars":["challenger"]}	\N	2025-10-15 15:54:06	2025-12-21 21:59:20	0	t	f	SB-TW5D	\N	strategybuzzer	t	\N	t	en	f	t	\N	\N	\N	\N	20
8	stevegroup	stevegroupe@gmail.com	\N	$2y$12$T.SXwXKiDdtTr./oIuyReeB42VQrFJbMMpLCX23Orrmq4L0wTQAie	0	3	\N	Rookie	{"sound":{"buzzer":true,"results":true,"ambiance":true,"music_id":"strategybuzzer","buzzer_id":"buzzer_default_1"},"theme":{"decor":null,"style":"Classique"},"avatar":{"type":"regular","id":"kodiak.png","name":null,"url":"images\\/avatars\\/animal\\/kodiak.png"},"country":"CA","gameplay":{"music_id":"strategybuzzer"},"language":"Fran\\u00e7ais","pseudonym":"stevegroup","show_online":false,"show_in_league":"Oui","strategic_avatar":{"id":null,"url":null,"name":null},"unlocked_avatars":["animal"]}	\N	2025-10-24 18:08:52	2025-12-08 20:25:55	0	t	f	SB-ZZES	\N	strategybuzzer	t	\N	t	fr	f	t	\N	\N	\N	\N	710
1	Test User	test@strategybuzzer.com	\N	$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi	0	3	\N	Rookie	{"unlocked": ["portraits", "cartoon"]}	\N	2025-10-07 17:38:51	2025-11-17 15:56:07	0	f	f	SB-UCX5	\N	strategybuzzer	t	\N	t	fr	f	t	\N	\N	\N	\N	10350
\.


--
-- Name: coin_ledger_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.coin_ledger_id_seq', 17, true);


--
-- Name: daily_quest_rotation_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.daily_quest_rotation_id_seq', 1, false);


--
-- Name: duo_matches_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.duo_matches_id_seq', 213, true);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, true);


--
-- Name: invitations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.invitations_id_seq', 1, false);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.jobs_id_seq', 624, true);


--
-- Name: league_individual_matches_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.league_individual_matches_id_seq', 1, true);


--
-- Name: league_individual_stats_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.league_individual_stats_id_seq', 2, true);


--
-- Name: league_team_match_participants_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.league_team_match_participants_id_seq', 1, false);


--
-- Name: league_team_matches_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.league_team_matches_id_seq', 1, false);


--
-- Name: master_game_codes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.master_game_codes_id_seq', 1, false);


--
-- Name: master_game_players_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.master_game_players_id_seq', 1, false);


--
-- Name: master_game_questions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.master_game_questions_id_seq', 370, true);


--
-- Name: master_game_teams_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.master_game_teams_id_seq', 1, false);


--
-- Name: master_games_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.master_games_id_seq', 57, true);


--
-- Name: match_performances_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.match_performances_id_seq', 25, true);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 62, true);


--
-- Name: payments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.payments_id_seq', 11, true);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 1, false);


--
-- Name: player_contacts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.player_contacts_id_seq', 24, true);


--
-- Name: player_divisions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.player_divisions_id_seq', 10, true);


--
-- Name: player_duo_stats_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.player_duo_stats_id_seq', 7, true);


--
-- Name: player_group_members_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.player_group_members_id_seq', 1, false);


--
-- Name: player_groups_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.player_groups_id_seq', 1, false);


--
-- Name: player_messages_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.player_messages_id_seq', 9, true);


--
-- Name: player_statistics_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.player_statistics_id_seq', 35, true);


--
-- Name: profile_stats_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.profile_stats_id_seq', 4, true);


--
-- Name: purchases_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.purchases_id_seq', 1, false);


--
-- Name: question_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.question_history_id_seq', 1249, true);


--
-- Name: quests_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.quests_id_seq', 183, true);


--
-- Name: team_invitations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.team_invitations_id_seq', 7, true);


--
-- Name: team_join_requests_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.team_join_requests_id_seq', 1, false);


--
-- Name: team_members_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.team_members_id_seq', 8, true);


--
-- Name: teams_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.teams_id_seq', 1, true);


--
-- Name: user_avatars_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.user_avatars_id_seq', 120, true);


--
-- Name: user_preferences_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.user_preferences_id_seq', 1, false);


--
-- Name: user_quest_progress_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.user_quest_progress_id_seq', 21, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.users_id_seq', 10, true);


--
-- Name: coin_ledger coin_ledger_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coin_ledger
    ADD CONSTRAINT coin_ledger_pkey PRIMARY KEY (id);


--
-- Name: daily_quest_rotation daily_quest_rotation_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_quest_rotation
    ADD CONSTRAINT daily_quest_rotation_pkey PRIMARY KEY (id);


--
-- Name: daily_quest_rotation daily_quest_rotation_rotation_date_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_quest_rotation
    ADD CONSTRAINT daily_quest_rotation_rotation_date_key UNIQUE (rotation_date);


--
-- Name: duo_matches duo_matches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.duo_matches
    ADD CONSTRAINT duo_matches_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: invitations invitations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations
    ADD CONSTRAINT invitations_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: league_individual_matches league_individual_matches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_individual_matches
    ADD CONSTRAINT league_individual_matches_pkey PRIMARY KEY (id);


--
-- Name: league_individual_stats league_individual_stats_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_individual_stats
    ADD CONSTRAINT league_individual_stats_pkey PRIMARY KEY (id);


--
-- Name: league_individual_stats league_individual_stats_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_individual_stats
    ADD CONSTRAINT league_individual_stats_user_id_unique UNIQUE (user_id);


--
-- Name: league_team_match_participants league_team_match_participants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_team_match_participants
    ADD CONSTRAINT league_team_match_participants_pkey PRIMARY KEY (id);


--
-- Name: league_team_matches league_team_matches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_team_matches
    ADD CONSTRAINT league_team_matches_pkey PRIMARY KEY (id);


--
-- Name: lobby_game_starts lobby_game_starts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lobby_game_starts
    ADD CONSTRAINT lobby_game_starts_pkey PRIMARY KEY (id);


--
-- Name: master_game_codes master_game_codes_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_codes
    ADD CONSTRAINT master_game_codes_code_unique UNIQUE (code);


--
-- Name: master_game_codes master_game_codes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_codes
    ADD CONSTRAINT master_game_codes_pkey PRIMARY KEY (id);


--
-- Name: master_game_players master_game_players_master_game_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_players
    ADD CONSTRAINT master_game_players_master_game_id_user_id_unique UNIQUE (master_game_id, user_id);


--
-- Name: master_game_players master_game_players_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_players
    ADD CONSTRAINT master_game_players_pkey PRIMARY KEY (id);


--
-- Name: master_game_questions master_game_questions_master_game_id_question_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_questions
    ADD CONSTRAINT master_game_questions_master_game_id_question_number_unique UNIQUE (master_game_id, question_number);


--
-- Name: master_game_questions master_game_questions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_questions
    ADD CONSTRAINT master_game_questions_pkey PRIMARY KEY (id);


--
-- Name: master_game_teams master_game_teams_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_teams
    ADD CONSTRAINT master_game_teams_pkey PRIMARY KEY (id);


--
-- Name: master_games master_games_firebase_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_games
    ADD CONSTRAINT master_games_firebase_id_unique UNIQUE (firebase_id);


--
-- Name: master_games master_games_game_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_games
    ADD CONSTRAINT master_games_game_code_unique UNIQUE (game_code);


--
-- Name: master_games master_games_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_games
    ADD CONSTRAINT master_games_pkey PRIMARY KEY (id);


--
-- Name: match_performances match_performances_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.match_performances
    ADD CONSTRAINT match_performances_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: payments payments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_pkey PRIMARY KEY (id);


--
-- Name: payments payments_stripe_payment_intent_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_stripe_payment_intent_id_unique UNIQUE (stripe_payment_intent_id);


--
-- Name: payments payments_stripe_session_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_stripe_session_id_unique UNIQUE (stripe_session_id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: player_contacts player_contacts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_contacts
    ADD CONSTRAINT player_contacts_pkey PRIMARY KEY (id);


--
-- Name: player_contacts player_contacts_user_id_contact_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_contacts
    ADD CONSTRAINT player_contacts_user_id_contact_user_id_unique UNIQUE (user_id, contact_user_id);


--
-- Name: player_divisions player_divisions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_divisions
    ADD CONSTRAINT player_divisions_pkey PRIMARY KEY (id);


--
-- Name: player_divisions player_divisions_user_id_mode_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_divisions
    ADD CONSTRAINT player_divisions_user_id_mode_unique UNIQUE (user_id, mode);


--
-- Name: player_duo_stats player_duo_stats_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_duo_stats
    ADD CONSTRAINT player_duo_stats_pkey PRIMARY KEY (id);


--
-- Name: player_duo_stats player_duo_stats_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_duo_stats
    ADD CONSTRAINT player_duo_stats_user_id_unique UNIQUE (user_id);


--
-- Name: player_group_members player_group_members_group_id_member_user_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_group_members
    ADD CONSTRAINT player_group_members_group_id_member_user_id_key UNIQUE (group_id, member_user_id);


--
-- Name: player_group_members player_group_members_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_group_members
    ADD CONSTRAINT player_group_members_pkey PRIMARY KEY (id);


--
-- Name: player_groups player_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_groups
    ADD CONSTRAINT player_groups_pkey PRIMARY KEY (id);


--
-- Name: player_messages player_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_messages
    ADD CONSTRAINT player_messages_pkey PRIMARY KEY (id);


--
-- Name: player_statistics player_statistics_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_statistics
    ADD CONSTRAINT player_statistics_pkey PRIMARY KEY (id);


--
-- Name: profile_stats profile_stats_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.profile_stats
    ADD CONSTRAINT profile_stats_pkey PRIMARY KEY (id);


--
-- Name: profile_stats profile_stats_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.profile_stats
    ADD CONSTRAINT profile_stats_user_id_unique UNIQUE (user_id);


--
-- Name: purchases purchases_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchases
    ADD CONSTRAINT purchases_pkey PRIMARY KEY (id);


--
-- Name: question_history question_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.question_history
    ADD CONSTRAINT question_history_pkey PRIMARY KEY (id);


--
-- Name: quests quests_detection_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quests
    ADD CONSTRAINT quests_detection_code_unique UNIQUE (detection_code);


--
-- Name: quests quests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quests
    ADD CONSTRAINT quests_pkey PRIMARY KEY (id);


--
-- Name: team_invitations team_invitations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_invitations
    ADD CONSTRAINT team_invitations_pkey PRIMARY KEY (id);


--
-- Name: team_join_requests team_join_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_join_requests
    ADD CONSTRAINT team_join_requests_pkey PRIMARY KEY (id);


--
-- Name: team_join_requests team_join_requests_team_id_user_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_join_requests
    ADD CONSTRAINT team_join_requests_team_id_user_id_key UNIQUE (team_id, user_id);


--
-- Name: team_members team_members_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_members
    ADD CONSTRAINT team_members_pkey PRIMARY KEY (id);


--
-- Name: team_members team_members_team_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_members
    ADD CONSTRAINT team_members_team_id_user_id_unique UNIQUE (team_id, user_id);


--
-- Name: teams teams_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT teams_pkey PRIMARY KEY (id);


--
-- Name: teams teams_tag_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT teams_tag_unique UNIQUE (tag);


--
-- Name: teams teams_team_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT teams_team_code_key UNIQUE (team_code);


--
-- Name: user_avatars user_avatars_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_avatars
    ADD CONSTRAINT user_avatars_pkey PRIMARY KEY (id);


--
-- Name: user_preferences user_preferences_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_preferences
    ADD CONSTRAINT user_preferences_pkey PRIMARY KEY (id);


--
-- Name: user_quest_progress user_quest_progress_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_quest_progress
    ADD CONSTRAINT user_quest_progress_pkey PRIMARY KEY (id);


--
-- Name: user_quest_progress user_quest_progress_user_id_quest_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_quest_progress
    ADD CONSTRAINT user_quest_progress_user_id_quest_id_unique UNIQUE (user_id, quest_id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: users users_player_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_player_code_unique UNIQUE (player_code);


--
-- Name: coin_ledger_ref_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX coin_ledger_ref_type_index ON public.coin_ledger USING btree (ref_type);


--
-- Name: coin_ledger_user_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX coin_ledger_user_id_created_at_index ON public.coin_ledger USING btree (user_id, created_at);


--
-- Name: duo_matches_player1_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX duo_matches_player1_id_status_index ON public.duo_matches USING btree (player1_id, status);


--
-- Name: duo_matches_player2_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX duo_matches_player2_id_status_index ON public.duo_matches USING btree (player2_id, status);


--
-- Name: duo_matches_room_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX duo_matches_room_id_index ON public.duo_matches USING btree (room_id);


--
-- Name: idx_ltmp_match_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ltmp_match_id ON public.league_team_match_participants USING btree (match_id);


--
-- Name: idx_ltmp_team_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ltmp_team_id ON public.league_team_match_participants USING btree (team_id);


--
-- Name: idx_ltmp_user_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ltmp_user_id ON public.league_team_match_participants USING btree (user_id);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: league_individual_matches_player1_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX league_individual_matches_player1_id_status_index ON public.league_individual_matches USING btree (player1_id, status);


--
-- Name: league_individual_matches_player2_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX league_individual_matches_player2_id_status_index ON public.league_individual_matches USING btree (player2_id, status);


--
-- Name: league_team_matches_team1_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX league_team_matches_team1_id_status_index ON public.league_team_matches USING btree (team1_id, status);


--
-- Name: league_team_matches_team2_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX league_team_matches_team2_id_status_index ON public.league_team_matches USING btree (team2_id, status);


--
-- Name: lobby_game_starts_lobby_code_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lobby_game_starts_lobby_code_created_at_index ON public.lobby_game_starts USING btree (lobby_code, created_at);


--
-- Name: lobby_game_starts_lobby_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lobby_game_starts_lobby_code_index ON public.lobby_game_starts USING btree (lobby_code);


--
-- Name: match_performances_user_id_game_mode_played_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX match_performances_user_id_game_mode_played_at_index ON public.match_performances USING btree (user_id, game_mode, played_at);


--
-- Name: match_performances_user_id_played_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX match_performances_user_id_played_at_index ON public.match_performances USING btree (user_id, played_at);


--
-- Name: payments_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payments_created_at_index ON public.payments USING btree (created_at);


--
-- Name: payments_user_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payments_user_id_status_index ON public.payments USING btree (user_id, status);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: player_contacts_contact_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX player_contacts_contact_user_id_index ON public.player_contacts USING btree (contact_user_id);


--
-- Name: player_contacts_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX player_contacts_user_id_index ON public.player_contacts USING btree (user_id);


--
-- Name: player_divisions_mode_division_points_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX player_divisions_mode_division_points_index ON public.player_divisions USING btree (mode, division, points);


--
-- Name: player_duo_stats_level_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX player_duo_stats_level_index ON public.player_duo_stats USING btree (level);


--
-- Name: player_messages_receiver_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX player_messages_receiver_id_index ON public.player_messages USING btree (receiver_id);


--
-- Name: player_messages_receiver_id_is_read_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX player_messages_receiver_id_is_read_index ON public.player_messages USING btree (receiver_id, is_read);


--
-- Name: player_messages_related_match_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX player_messages_related_match_id_index ON public.player_messages USING btree (related_match_id);


--
-- Name: player_messages_sender_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX player_messages_sender_id_index ON public.player_messages USING btree (sender_id);


--
-- Name: player_statistics_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX player_statistics_created_at_index ON public.player_statistics USING btree (created_at);


--
-- Name: player_statistics_user_id_game_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX player_statistics_user_id_game_id_index ON public.player_statistics USING btree (user_id, game_id);


--
-- Name: player_statistics_user_id_game_mode_scope_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX player_statistics_user_id_game_mode_scope_index ON public.player_statistics USING btree (user_id, game_mode, scope);


--
-- Name: profile_stats_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX profile_stats_user_id_index ON public.profile_stats USING btree (user_id);


--
-- Name: question_history_question_hash_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX question_history_question_hash_index ON public.question_history USING btree (question_hash);


--
-- Name: question_history_question_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX question_history_question_id_index ON public.question_history USING btree (question_id);


--
-- Name: question_history_user_id_correct_answer_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX question_history_user_id_correct_answer_index ON public.question_history USING btree (user_id, correct_answer);


--
-- Name: question_history_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX question_history_user_id_index ON public.question_history USING btree (user_id);


--
-- Name: question_history_user_id_question_hash_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX question_history_user_id_question_hash_index ON public.question_history USING btree (user_id, question_hash);


--
-- Name: team_invitations_user_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX team_invitations_user_id_status_index ON public.team_invitations USING btree (user_id, status);


--
-- Name: teams_division_points_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX teams_division_points_index ON public.teams USING btree (division, points);


--
-- Name: coin_ledger coin_ledger_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.coin_ledger
    ADD CONSTRAINT coin_ledger_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: duo_matches duo_matches_player1_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.duo_matches
    ADD CONSTRAINT duo_matches_player1_id_foreign FOREIGN KEY (player1_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: duo_matches duo_matches_player2_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.duo_matches
    ADD CONSTRAINT duo_matches_player2_id_foreign FOREIGN KEY (player2_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: duo_matches duo_matches_winner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.duo_matches
    ADD CONSTRAINT duo_matches_winner_id_foreign FOREIGN KEY (winner_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: league_individual_matches league_individual_matches_player1_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_individual_matches
    ADD CONSTRAINT league_individual_matches_player1_id_foreign FOREIGN KEY (player1_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: league_individual_matches league_individual_matches_player2_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_individual_matches
    ADD CONSTRAINT league_individual_matches_player2_id_foreign FOREIGN KEY (player2_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: league_individual_matches league_individual_matches_winner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_individual_matches
    ADD CONSTRAINT league_individual_matches_winner_id_foreign FOREIGN KEY (winner_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: league_individual_stats league_individual_stats_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_individual_stats
    ADD CONSTRAINT league_individual_stats_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: league_team_match_participants league_team_match_participants_match_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_team_match_participants
    ADD CONSTRAINT league_team_match_participants_match_id_fkey FOREIGN KEY (match_id) REFERENCES public.league_team_matches(id) ON DELETE CASCADE;


--
-- Name: league_team_match_participants league_team_match_participants_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_team_match_participants
    ADD CONSTRAINT league_team_match_participants_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: league_team_matches league_team_matches_team1_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_team_matches
    ADD CONSTRAINT league_team_matches_team1_id_foreign FOREIGN KEY (team1_id) REFERENCES public.teams(id) ON DELETE CASCADE;


--
-- Name: league_team_matches league_team_matches_team2_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_team_matches
    ADD CONSTRAINT league_team_matches_team2_id_foreign FOREIGN KEY (team2_id) REFERENCES public.teams(id) ON DELETE CASCADE;


--
-- Name: league_team_matches league_team_matches_winner_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_team_matches
    ADD CONSTRAINT league_team_matches_winner_team_id_foreign FOREIGN KEY (winner_team_id) REFERENCES public.teams(id) ON DELETE SET NULL;


--
-- Name: master_game_codes master_game_codes_master_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_codes
    ADD CONSTRAINT master_game_codes_master_game_id_foreign FOREIGN KEY (master_game_id) REFERENCES public.master_games(id) ON DELETE CASCADE;


--
-- Name: master_game_players master_game_players_master_game_code_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_players
    ADD CONSTRAINT master_game_players_master_game_code_id_foreign FOREIGN KEY (master_game_code_id) REFERENCES public.master_game_codes(id) ON DELETE SET NULL;


--
-- Name: master_game_players master_game_players_master_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_players
    ADD CONSTRAINT master_game_players_master_game_id_foreign FOREIGN KEY (master_game_id) REFERENCES public.master_games(id) ON DELETE CASCADE;


--
-- Name: master_game_players master_game_players_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_players
    ADD CONSTRAINT master_game_players_team_id_foreign FOREIGN KEY (team_id) REFERENCES public.master_game_teams(id) ON DELETE SET NULL;


--
-- Name: master_game_players master_game_players_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_players
    ADD CONSTRAINT master_game_players_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: master_game_questions master_game_questions_master_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_questions
    ADD CONSTRAINT master_game_questions_master_game_id_foreign FOREIGN KEY (master_game_id) REFERENCES public.master_games(id) ON DELETE CASCADE;


--
-- Name: master_game_teams master_game_teams_master_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_game_teams
    ADD CONSTRAINT master_game_teams_master_game_id_foreign FOREIGN KEY (master_game_id) REFERENCES public.master_games(id) ON DELETE CASCADE;


--
-- Name: master_games master_games_host_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.master_games
    ADD CONSTRAINT master_games_host_user_id_foreign FOREIGN KEY (host_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: match_performances match_performances_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.match_performances
    ADD CONSTRAINT match_performances_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: payments payments_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: player_contacts player_contacts_contact_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_contacts
    ADD CONSTRAINT player_contacts_contact_user_id_foreign FOREIGN KEY (contact_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: player_contacts player_contacts_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_contacts
    ADD CONSTRAINT player_contacts_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: player_divisions player_divisions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_divisions
    ADD CONSTRAINT player_divisions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: player_duo_stats player_duo_stats_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_duo_stats
    ADD CONSTRAINT player_duo_stats_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: player_group_members player_group_members_group_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_group_members
    ADD CONSTRAINT player_group_members_group_id_fkey FOREIGN KEY (group_id) REFERENCES public.player_groups(id) ON DELETE CASCADE;


--
-- Name: player_group_members player_group_members_member_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_group_members
    ADD CONSTRAINT player_group_members_member_user_id_fkey FOREIGN KEY (member_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: player_groups player_groups_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_groups
    ADD CONSTRAINT player_groups_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: player_messages player_messages_receiver_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_messages
    ADD CONSTRAINT player_messages_receiver_id_foreign FOREIGN KEY (receiver_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: player_messages player_messages_related_match_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_messages
    ADD CONSTRAINT player_messages_related_match_id_foreign FOREIGN KEY (related_match_id) REFERENCES public.duo_matches(id) ON DELETE SET NULL;


--
-- Name: player_messages player_messages_sender_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_messages
    ADD CONSTRAINT player_messages_sender_id_foreign FOREIGN KEY (sender_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: player_statistics player_statistics_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.player_statistics
    ADD CONSTRAINT player_statistics_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: profile_stats profile_stats_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.profile_stats
    ADD CONSTRAINT profile_stats_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: purchases purchases_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchases
    ADD CONSTRAINT purchases_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: question_history question_history_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.question_history
    ADD CONSTRAINT question_history_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: team_invitations team_invitations_invited_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_invitations
    ADD CONSTRAINT team_invitations_invited_by_foreign FOREIGN KEY (invited_by) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: team_invitations team_invitations_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_invitations
    ADD CONSTRAINT team_invitations_team_id_foreign FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE CASCADE;


--
-- Name: team_invitations team_invitations_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_invitations
    ADD CONSTRAINT team_invitations_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: team_join_requests team_join_requests_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_join_requests
    ADD CONSTRAINT team_join_requests_team_id_fkey FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE CASCADE;


--
-- Name: team_join_requests team_join_requests_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_join_requests
    ADD CONSTRAINT team_join_requests_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: team_members team_members_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_members
    ADD CONSTRAINT team_members_team_id_foreign FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE CASCADE;


--
-- Name: team_members team_members_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_members
    ADD CONSTRAINT team_members_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: teams teams_captain_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT teams_captain_id_foreign FOREIGN KEY (captain_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_avatars user_avatars_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_avatars
    ADD CONSTRAINT user_avatars_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_preferences user_preferences_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_preferences
    ADD CONSTRAINT user_preferences_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_quest_progress user_quest_progress_quest_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_quest_progress
    ADD CONSTRAINT user_quest_progress_quest_id_foreign FOREIGN KEY (quest_id) REFERENCES public.quests(id) ON DELETE CASCADE;


--
-- Name: user_quest_progress user_quest_progress_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_quest_progress
    ADD CONSTRAINT user_quest_progress_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict ZXx4ypxjLqoHjJiJ3Oc7Xbj6IIwQBEXNVNZRgipDXcNRPvC9wARwdPAE6WHDPPt

