-- Full PostgreSQL schema baseline generated from the current trunk migration result.
-- The collations, data, checks, and post SQL sections complete installation.
-- See schema.postgresql.version for the snapshot source.
--
-- PostgreSQL database dump
--


-- Dumped from database version 18.6 (Debian 18.6-1.pgdg13+2)
-- Dumped by pg_dump version 18.6 (Debian 18.6-1.pgdg13+2)


--
-- Name: public; Type: SCHEMA; Schema: -; Owner: -
--



--
-- Name: on_update_current_timestamp_sales_channel_api_context(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.on_update_current_timestamp_sales_channel_api_context() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
   NEW.updated_at = now();
   RETURN NEW;
END;
$$;




--
-- Name: acl_role; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.acl_role (
    id bytea NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    privileges jsonb NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    deleted_at timestamp(3) without time zone
);


--
-- Name: acl_user_role; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.acl_user_role (
    user_id bytea NOT NULL,
    acl_role_id bytea NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: admin_elasticsearch_index_task; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.admin_elasticsearch_index_task (
    id bytea NOT NULL,
    index character varying(500) NOT NULL,
    alias character varying(500) NOT NULL,
    entity character varying(500) NOT NULL,
    doc_count integer NOT NULL
);


--
-- Name: app; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app (
    id bytea NOT NULL,
    name character varying(255) NOT NULL,
    path character varying(4096),
    author character varying(255),
    copyright character varying(255),
    license character varying(255),
    privacy character varying(255),
    version character varying(255) NOT NULL,
    base_app_url character varying(1024),
    active smallint DEFAULT '0'::smallint NOT NULL,
    allow_disable smallint DEFAULT '1'::smallint NOT NULL,
    configurable smallint DEFAULT '0'::smallint NOT NULL,
    icon bytea,
    app_secret character varying(255),
    modules jsonb,
    main_module jsonb,
    cookies jsonb,
    allowed_hosts jsonb,
    integration_id bytea NOT NULL,
    acl_role_id bytea NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    template_load_priority integer DEFAULT 0,
    checkout_gateway_url character varying(255),
    self_managed smallint DEFAULT '0'::smallint NOT NULL,
    source_type character varying(20) DEFAULT 'local'::character varying NOT NULL,
    source_config jsonb DEFAULT JSON_OBJECT( RETURNING json) NOT NULL,
    in_app_purchases_gateway_url character varying(255),
    requested_privileges jsonb DEFAULT JSON_ARRAY( RETURNING json) NOT NULL,
    context_gateway_url character varying(255),
    unconfirmed_app_secrets jsonb
);


--
-- Name: app_action_button; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_action_button (
    id bytea NOT NULL,
    entity character varying(255) NOT NULL,
    view character varying(255) NOT NULL,
    url character varying(255) NOT NULL,
    action character varying(255) NOT NULL,
    app_id bytea NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: app_action_button_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_action_button_translation (
    label character varying(255),
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    app_action_button_id bytea NOT NULL,
    language_id bytea NOT NULL
);


--
-- Name: app_administration_snippet; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_administration_snippet (
    id bytea NOT NULL,
    app_id bytea NOT NULL,
    locale_id bytea NOT NULL,
    value jsonb NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: app_cms_block; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_cms_block (
    id bytea NOT NULL,
    app_id bytea NOT NULL,
    name character varying(255) NOT NULL,
    block jsonb NOT NULL,
    template text NOT NULL,
    styles text NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: app_cms_block_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_cms_block_translation (
    label character varying(255),
    app_cms_block_id bytea NOT NULL,
    language_id bytea NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: app_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_config (
    key character varying(255) NOT NULL,
    value text NOT NULL
);


--
-- Name: app_feature; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_feature (
    id bytea NOT NULL,
    app_id bytea,
    app_name character varying(255) NOT NULL,
    type character varying(64) NOT NULL,
    name character varying(255) NOT NULL,
    payload jsonb NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: app_flow_action; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_flow_action (
    id bytea NOT NULL,
    app_id bytea NOT NULL,
    name character varying(255) NOT NULL,
    badge character varying(255),
    url character varying(500) NOT NULL,
    delayable smallint DEFAULT '0'::smallint NOT NULL,
    parameters jsonb,
    config jsonb,
    headers jsonb,
    requirements jsonb,
    icon bytea,
    sw_icon character varying(255),
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: app_flow_action_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_flow_action_translation (
    app_flow_action_id bytea NOT NULL,
    language_id bytea NOT NULL,
    label character varying(255),
    description text,
    headline character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: app_flow_event; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_flow_event (
    id bytea NOT NULL,
    app_id bytea NOT NULL,
    name character varying(255) NOT NULL,
    aware jsonb NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: app_mcp_prompt; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_mcp_prompt (
    id bytea NOT NULL,
    app_id bytea NOT NULL,
    name character varying(255) NOT NULL,
    url character varying(2048) NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: app_mcp_prompt_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_mcp_prompt_translation (
    app_mcp_prompt_id bytea NOT NULL,
    language_id bytea NOT NULL,
    label character varying(255),
    description text,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: app_mcp_resource; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_mcp_resource (
    id bytea NOT NULL,
    app_id bytea NOT NULL,
    name character varying(255) NOT NULL,
    uri character varying(2048) NOT NULL,
    url character varying(2048) NOT NULL,
    mime_type character varying(255),
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: app_mcp_resource_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_mcp_resource_translation (
    app_mcp_resource_id bytea NOT NULL,
    language_id bytea NOT NULL,
    label character varying(255),
    description text,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: app_mcp_tool; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_mcp_tool (
    id bytea NOT NULL,
    app_id bytea NOT NULL,
    name character varying(255) NOT NULL,
    url character varying(2048) NOT NULL,
    input_schema jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    required_privileges jsonb
);


--
-- Name: app_mcp_tool_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_mcp_tool_translation (
    app_mcp_tool_id bytea NOT NULL,
    language_id bytea NOT NULL,
    label character varying(255),
    description text,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: app_payment_method; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_payment_method (
    id bytea NOT NULL,
    app_id bytea,
    payment_method_id bytea NOT NULL,
    app_name character varying(255) NOT NULL,
    identifier character varying(255) NOT NULL,
    pay_url character varying(255),
    finalize_url character varying(255),
    validate_url character varying(255),
    capture_url character varying(255),
    refund_url character varying(255),
    original_media_id bytea,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    recurring_url character varying(255)
);


--
-- Name: app_script_condition; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_script_condition (
    id bytea NOT NULL,
    app_id bytea NOT NULL,
    identifier character varying(255) NOT NULL,
    active smallint DEFAULT '1'::smallint NOT NULL,
    "group" character varying(255),
    script text,
    constraints bytea,
    config jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: app_script_condition_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_script_condition_translation (
    app_script_condition_id bytea CONSTRAINT app_script_condition_translati_app_script_condition_id_not_null NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: app_shipping_method; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_shipping_method (
    id bytea NOT NULL,
    app_id bytea,
    app_name character varying(255) NOT NULL,
    shipping_method_id bytea NOT NULL,
    original_media_id bytea,
    identifier character varying(255) NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: app_template; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_template (
    id bytea NOT NULL,
    template text NOT NULL,
    path character varying(1024) NOT NULL,
    active smallint NOT NULL,
    app_id bytea NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    hash character varying(32)
);


--
-- Name: app_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.app_translation (
    app_id bytea NOT NULL,
    language_id bytea NOT NULL,
    label character varying(255),
    description text,
    privacy_policy_extensions text,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: cart; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cart (
    token character varying(50) NOT NULL,
    price double precision,
    line_item_count character varying(42),
    currency_id bytea,
    shipping_method_id bytea,
    payment_method_id bytea,
    country_id bytea,
    customer_id bytea,
    sales_channel_id bytea,
    rule_ids jsonb NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    auto_increment bigint NOT NULL,
    compressed smallint DEFAULT '0'::smallint NOT NULL,
    payload bytea
);


--
-- Name: cart_auto_increment_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cart_auto_increment_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cart_auto_increment_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cart_auto_increment_seq OWNED BY public.cart.auto_increment;


--
-- Name: category; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.category (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    auto_increment integer NOT NULL,
    parent_id bytea,
    parent_version_id bytea,
    media_id bytea,
    cms_page_id bytea,
    cms_page_version_id bytea DEFAULT '\x30783046413931434533453936413442433242453442443943453735324333343235'::bytea NOT NULL,
    product_stream_id bytea,
    product_assignment_type character varying(32) DEFAULT 'product'::character varying NOT NULL,
    path text,
    after_category_id bytea,
    after_category_version_id bytea,
    level bigint DEFAULT '1'::bigint NOT NULL,
    active smallint DEFAULT '1'::smallint NOT NULL,
    child_count bigint DEFAULT '0'::bigint NOT NULL,
    display_nested_products smallint DEFAULT '1'::smallint NOT NULL,
    visible smallint DEFAULT '1'::smallint NOT NULL,
    type character varying(32) NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    custom_entity_type_id bytea
);


--
-- Name: category_auto_increment_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.category_auto_increment_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: category_auto_increment_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.category_auto_increment_seq OWNED BY public.category.auto_increment;


--
-- Name: category_tag; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.category_tag (
    category_id bytea NOT NULL,
    category_version_id bytea NOT NULL,
    tag_id bytea NOT NULL
);


--
-- Name: category_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.category_translation (
    category_id bytea NOT NULL,
    category_version_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    breadcrumb jsonb,
    internal_link bytea,
    link_new_tab smallint,
    link_type character varying(255),
    external_link text,
    description text,
    meta_title character varying(255),
    meta_description character varying(255),
    keywords character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    slot_config jsonb
);


--
-- Name: cms_block; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cms_block (
    id bytea NOT NULL,
    version_id bytea DEFAULT '\x30783046413931434533453936413442433242453442443943453735324333343235'::bytea NOT NULL,
    cms_section_id bytea,
    cms_section_version_id bytea DEFAULT '\x30783046413931434533453936413442433242453442443943453735324333343235'::bytea NOT NULL,
    "position" integer NOT NULL,
    section_position character varying(50) DEFAULT 'main'::character varying,
    type character varying(255) NOT NULL,
    name character varying(255),
    locked smallint DEFAULT '0'::smallint NOT NULL,
    margin_top character varying(255),
    margin_bottom character varying(255),
    margin_left character varying(255),
    margin_right character varying(255),
    background_color character varying(255),
    background_media_id bytea,
    background_media_mode character varying(255),
    visibility jsonb,
    css_class character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: cms_page; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cms_page (
    id bytea NOT NULL,
    version_id bytea DEFAULT '\x30783046413931434533453936413442433242453442443943453735324333343235'::bytea NOT NULL,
    type character varying(255) NOT NULL,
    entity character varying(255),
    preview_media_id bytea,
    locked smallint DEFAULT '0'::smallint NOT NULL,
    css_class character varying(255),
    config jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: cms_page_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cms_page_translation (
    cms_page_id bytea NOT NULL,
    cms_page_version_id bytea DEFAULT '\x30783046413931434533453936413442433242453442443943453735324333343235'::bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: cms_section; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cms_section (
    id bytea NOT NULL,
    version_id bytea DEFAULT '\x30783046413931434533453936413442433242453442443943453735324333343235'::bytea NOT NULL,
    cms_page_id bytea NOT NULL,
    cms_page_version_id bytea DEFAULT '\x30783046413931434533453936413442433242453442443943453735324333343235'::bytea NOT NULL,
    "position" integer NOT NULL,
    type character varying(255) DEFAULT 'default'::character varying NOT NULL,
    name character varying(255),
    locked smallint DEFAULT '0'::smallint NOT NULL,
    sizing_mode character varying(255) DEFAULT 'boxed'::character varying NOT NULL,
    mobile_behavior character varying(255) DEFAULT 'wrap'::character varying NOT NULL,
    background_color character varying(255),
    background_media_id bytea,
    background_media_mode character varying(255),
    visibility jsonb,
    css_class character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: cms_slot; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cms_slot (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    cms_block_id bytea NOT NULL,
    cms_block_version_id bytea DEFAULT '\x30783046413931434533453936413442433242453442443943453735324333343235'::bytea NOT NULL,
    type character varying(255) NOT NULL,
    slot character varying(255) NOT NULL,
    locked smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: cms_slot_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cms_slot_translation (
    cms_slot_id bytea NOT NULL,
    cms_slot_version_id bytea NOT NULL,
    language_id bytea NOT NULL,
    config jsonb,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: consent_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.consent_log (
    id bigint NOT NULL,
    consent_name character varying(100) NOT NULL,
    "timestamp" timestamp(3) without time zone NOT NULL,
    message text NOT NULL
);


--
-- Name: consent_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.consent_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: consent_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.consent_log_id_seq OWNED BY public.consent_log.id;


--
-- Name: consent_state; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.consent_state (
    id bytea NOT NULL,
    name character varying(100) NOT NULL,
    identifier character varying(100) NOT NULL,
    state character varying(20) NOT NULL,
    actor character varying(255) NOT NULL,
    updated_at timestamp(3) without time zone NOT NULL,
    revision character varying(255)
);


--
-- Name: country; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.country (
    id bytea NOT NULL,
    iso character varying(255),
    "position" integer DEFAULT 1 NOT NULL,
    active smallint DEFAULT '1'::smallint NOT NULL,
    iso3 character varying(45),
    display_state_in_registration smallint DEFAULT '0'::smallint NOT NULL,
    force_state_in_registration smallint DEFAULT '0'::smallint NOT NULL,
    check_vat_id_pattern smallint DEFAULT '0'::smallint NOT NULL,
    vat_id_pattern character varying(255),
    vat_id_required smallint DEFAULT '0'::smallint NOT NULL,
    customer_tax jsonb,
    company_tax jsonb,
    advanced_postal_code_pattern character varying(1024),
    check_advanced_postal_code_pattern smallint DEFAULT '0'::smallint NOT NULL,
    check_postal_code_pattern smallint DEFAULT '0'::smallint NOT NULL,
    default_postal_code_pattern character varying(1024),
    postal_code_required smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    shipping_available smallint DEFAULT '1'::smallint NOT NULL,
    is_eu smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: country_state; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.country_state (
    id bytea NOT NULL,
    country_id bytea NOT NULL,
    short_code character varying(255) NOT NULL,
    "position" integer DEFAULT 1 NOT NULL,
    active smallint DEFAULT '1'::smallint NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: country_state_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.country_state_translation (
    country_state_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: country_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.country_translation (
    country_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    address_format jsonb,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: currency; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.currency (
    id bytea NOT NULL,
    iso_code character(3) NOT NULL,
    factor double precision NOT NULL,
    symbol character varying(255) NOT NULL,
    "position" integer DEFAULT 1 NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    item_rounding jsonb,
    total_rounding jsonb,
    tax_free_from double precision DEFAULT '0'::double precision
);


--
-- Name: currency_country_rounding; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.currency_country_rounding (
    id bytea NOT NULL,
    currency_id bytea NOT NULL,
    country_id bytea NOT NULL,
    item_rounding jsonb NOT NULL,
    total_rounding jsonb NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: currency_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.currency_translation (
    currency_id bytea NOT NULL,
    language_id bytea NOT NULL,
    short_name character varying(255),
    name character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: custom_entity; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.custom_entity (
    id bytea NOT NULL,
    name character varying(255) NOT NULL,
    fields jsonb NOT NULL,
    app_id bytea,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    flags jsonb,
    plugin_id bytea,
    custom_fields_aware smallint DEFAULT '0'::smallint NOT NULL,
    label_property character varying(255),
    deleted_at timestamp(3) without time zone
);


--
-- Name: custom_field; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.custom_field (
    id bytea NOT NULL,
    name character varying(255) NOT NULL,
    type character varying(255) NOT NULL,
    config jsonb,
    active smallint DEFAULT '1'::smallint NOT NULL,
    set_id bytea,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    allow_customer_write smallint DEFAULT '0'::smallint NOT NULL,
    allow_cart_expose smallint DEFAULT '0'::smallint NOT NULL,
    store_api_aware smallint DEFAULT '1'::smallint NOT NULL,
    include_in_search smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: custom_field_set; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.custom_field_set (
    id bytea NOT NULL,
    name character varying(255) NOT NULL,
    config jsonb,
    active smallint DEFAULT '1'::smallint NOT NULL,
    app_id bytea,
    "position" integer DEFAULT 1 NOT NULL,
    global smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    extension_name character varying(255)
);


--
-- Name: custom_field_set_relation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.custom_field_set_relation (
    id bytea NOT NULL,
    set_id bytea NOT NULL,
    entity_name character varying(64) NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: customer; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer (
    id bytea NOT NULL,
    auto_increment bigint NOT NULL,
    customer_group_id bytea NOT NULL,
    requested_customer_group_id bytea,
    default_payment_method_id bytea,
    sales_channel_id bytea NOT NULL,
    language_id bytea NOT NULL,
    last_payment_method_id bytea,
    default_billing_address_id bytea NOT NULL,
    default_shipping_address_id bytea NOT NULL,
    customer_number character varying(255) NOT NULL,
    salutation_id bytea,
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    company character varying(255),
    password character varying(1024),
    legacy_password character varying(255),
    legacy_encoder character varying(255),
    email character varying(254) NOT NULL,
    title character varying(100),
    vat_ids jsonb,
    active smallint DEFAULT '1'::smallint NOT NULL,
    double_opt_in_registration smallint DEFAULT '0'::smallint NOT NULL,
    double_opt_in_email_sent_date timestamp(3) without time zone,
    double_opt_in_confirm_date timestamp(3) without time zone,
    hash character varying(255),
    guest smallint DEFAULT '0'::smallint NOT NULL,
    first_login timestamp(3) without time zone,
    last_login timestamp(3) without time zone,
    newsletter_sales_channel_ids jsonb,
    birthday date,
    last_order_date timestamp(3) without time zone,
    order_count integer DEFAULT 0 NOT NULL,
    order_total_amount double precision DEFAULT '0'::double precision,
    custom_fields jsonb,
    affiliate_code character varying(255),
    campaign_code character varying(255),
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    remote_address character varying(255),
    tag_ids jsonb,
    bound_sales_channel_id bytea,
    created_by_id bytea,
    updated_by_id bytea,
    account_type character varying(255) DEFAULT 'private'::character varying NOT NULL,
    review_count integer DEFAULT 0 NOT NULL
);


--
-- Name: customer_address; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_address (
    id bytea NOT NULL,
    customer_id bytea NOT NULL,
    country_id bytea NOT NULL,
    country_state_id bytea,
    company character varying(255),
    department character varying(255),
    salutation_id bytea,
    title character varying(100),
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    street character varying(255) NOT NULL,
    zipcode character varying(50),
    city character varying(70) NOT NULL,
    phone_number character varying(40),
    additional_address_line1 character varying(255),
    additional_address_line2 character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: customer_auto_increment_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.customer_auto_increment_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customer_auto_increment_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customer_auto_increment_seq OWNED BY public.customer.auto_increment;


--
-- Name: customer_group; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_group (
    id bytea NOT NULL,
    display_gross smallint DEFAULT '1'::smallint NOT NULL,
    registration_active smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: customer_group_registration_sales_channels; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_group_registration_sales_channels (
    customer_group_id bytea CONSTRAINT customer_group_registration_sales_ch_customer_group_id_not_null NOT NULL,
    sales_channel_id bytea CONSTRAINT customer_group_registration_sales_cha_sales_channel_id_not_null NOT NULL,
    created_at timestamp(3) without time zone NOT NULL
);


--
-- Name: customer_group_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_group_translation (
    customer_group_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    custom_fields jsonb,
    registration_title character varying(255),
    registration_introduction text,
    registration_only_company_registration smallint,
    registration_seo_meta_description text,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: customer_recovery; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_recovery (
    id bytea NOT NULL,
    customer_id bytea NOT NULL,
    hash character varying(255) NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: customer_tag; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_tag (
    customer_id bytea NOT NULL,
    tag_id bytea NOT NULL
);


--
-- Name: customer_wishlist; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_wishlist (
    id bytea NOT NULL,
    customer_id bytea NOT NULL,
    sales_channel_id bytea NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: customer_wishlist_product; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_wishlist_product (
    id bytea NOT NULL,
    customer_wishlist_id bytea NOT NULL,
    product_id bytea NOT NULL,
    product_version_id bytea NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: deleted_apps; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.deleted_apps (
    name character varying(255) NOT NULL,
    app_secret character varying(255) NOT NULL,
    unconfirmed_app_secrets jsonb
);


--
-- Name: delivery_time; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.delivery_time (
    id bytea NOT NULL,
    min integer NOT NULL,
    max integer NOT NULL,
    unit character varying(255) NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: delivery_time_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.delivery_time_translation (
    delivery_time_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: document; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document (
    id bytea NOT NULL,
    document_type_id bytea NOT NULL,
    referenced_document_id bytea,
    file_type character varying(255),
    order_id bytea,
    order_version_id bytea,
    config jsonb,
    sent smallint DEFAULT '0'::smallint NOT NULL,
    static smallint DEFAULT '0'::smallint NOT NULL,
    deep_link_code character varying(32) NOT NULL,
    document_media_file_id bytea,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    document_number character varying(255),
    document_a11y_media_file_id bytea,
    type_name character varying(255)
);


--
-- Name: document_base_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_base_config (
    id bytea NOT NULL,
    name character varying(64) NOT NULL,
    filename_prefix character varying(64) DEFAULT ''::character varying,
    filename_suffix character varying(64) DEFAULT ''::character varying,
    document_number character varying(64) DEFAULT ''::character varying,
    global smallint DEFAULT '0'::smallint,
    document_type_id bytea NOT NULL,
    logo_id bytea,
    config jsonb,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    page_size character varying(32),
    page_orientation character varying(32),
    items_per_page bigint,
    display_header smallint,
    display_footer smallint,
    display_page_count smallint,
    display_company_address smallint,
    display_return_address smallint,
    display_customer_vat_id smallint,
    filename_infixes jsonb,
    type_name character varying(255)
);


--
-- Name: document_base_config_sales_channel; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_base_config_sales_channel (
    id bytea NOT NULL,
    document_base_config_id bytea CONSTRAINT document_base_config_sales_cha_document_base_config_id_not_null NOT NULL,
    document_type_id bytea NOT NULL,
    sales_channel_id bytea,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    type_name character varying(255)
);


--
-- Name: document_file; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_file (
    id bytea NOT NULL,
    document_id bytea NOT NULL,
    media_id bytea NOT NULL,
    document_format character varying(255) NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: document_type; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_type (
    id bytea NOT NULL,
    technical_name character varying(255) NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: document_type_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_type_translation (
    document_type_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: elasticsearch_index_task; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.elasticsearch_index_task (
    id bytea NOT NULL,
    index character varying(500) NOT NULL,
    alias character varying(500) NOT NULL,
    entity character varying(500) NOT NULL,
    doc_count integer NOT NULL
);


--
-- Name: flow; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.flow (
    id bytea NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    event_name character varying(255) NOT NULL,
    priority integer DEFAULT 1 NOT NULL,
    payload bytea,
    invalid smallint DEFAULT '0'::smallint NOT NULL,
    active smallint DEFAULT '0'::smallint NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    app_flow_event_id bytea
);


--
-- Name: flow_sequence; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.flow_sequence (
    id bytea NOT NULL,
    flow_id bytea NOT NULL,
    app_flow_action_id bytea,
    parent_id bytea,
    rule_id bytea,
    action_name character varying(255),
    config jsonb,
    "position" integer DEFAULT 1 NOT NULL,
    display_group integer DEFAULT 1 NOT NULL,
    true_case smallint DEFAULT '0'::smallint NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: flow_template; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.flow_template (
    id bytea NOT NULL,
    name character varying(255) NOT NULL,
    config jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: import_export_file; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.import_export_file (
    id bytea NOT NULL,
    original_name character varying(255) NOT NULL,
    path character varying(255) NOT NULL,
    expire_date timestamp(3) without time zone NOT NULL,
    size integer,
    updated_at timestamp(3) without time zone,
    created_at timestamp(3) without time zone NOT NULL,
    access_token character varying(255)
);


--
-- Name: import_export_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.import_export_log (
    id bytea NOT NULL,
    activity character varying(255) NOT NULL,
    state character varying(255) NOT NULL,
    records integer NOT NULL,
    username character varying(255),
    profile_name character varying(255),
    user_id bytea,
    profile_id bytea,
    file_id bytea,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    config jsonb,
    result jsonb,
    invalid_records_log_id bytea
);


--
-- Name: import_export_profile; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.import_export_profile (
    id bytea NOT NULL,
    name character varying(255),
    system_default smallint DEFAULT '0'::smallint NOT NULL,
    source_entity character varying(255) NOT NULL,
    file_type character varying(255) NOT NULL,
    delimiter character varying(255),
    enclosure character varying(255),
    type character varying(255) DEFAULT 'import-export'::character varying NOT NULL,
    mapping text,
    update_by jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    config jsonb,
    technical_name character varying(255) NOT NULL
);


--
-- Name: import_export_profile_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.import_export_profile_translation (
    import_export_profile_id bytea CONSTRAINT import_export_profile_transla_import_export_profile_id_not_null NOT NULL,
    language_id bytea NOT NULL,
    label character varying(255),
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: increment; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.increment (
    pool character varying(255) NOT NULL,
    cluster character varying(255) NOT NULL,
    key character varying(255) NOT NULL,
    count bigint DEFAULT '1'::bigint NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: integration; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.integration (
    id bytea NOT NULL,
    write_access smallint DEFAULT '0'::smallint NOT NULL,
    access_key character varying(255) NOT NULL,
    secret_access_key character varying(255) NOT NULL,
    label character varying(255) NOT NULL,
    admin smallint DEFAULT '1'::smallint NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    last_usage_at timestamp(3) without time zone,
    deleted_at timestamp(3) without time zone,
    mcp_allowlist jsonb
);


--
-- Name: integration_role; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.integration_role (
    integration_id bytea NOT NULL,
    acl_role_id bytea NOT NULL
);


--
-- Name: invalidation_tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.invalidation_tags (
    id bytea NOT NULL,
    tag character varying(255) NOT NULL
);


--
-- Name: landing_page; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.landing_page (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    active smallint DEFAULT '1'::smallint NOT NULL,
    cms_page_id bytea,
    cms_page_version_id bytea DEFAULT '\x30783046413931434533453936413442433242453442443943453735324333343235'::bytea NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: landing_page_sales_channel; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.landing_page_sales_channel (
    landing_page_id bytea NOT NULL,
    landing_page_version_id bytea NOT NULL,
    sales_channel_id bytea NOT NULL
);


--
-- Name: landing_page_tag; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.landing_page_tag (
    landing_page_id bytea NOT NULL,
    landing_page_version_id bytea NOT NULL,
    tag_id bytea NOT NULL
);


--
-- Name: landing_page_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.landing_page_translation (
    landing_page_id bytea NOT NULL,
    landing_page_version_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    url character varying(255),
    meta_title character varying(255),
    meta_description character varying(255),
    keywords character varying(255),
    custom_fields jsonb,
    slot_config jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: language; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.language (
    id bytea NOT NULL,
    name character varying(50) NOT NULL,
    parent_id bytea,
    locale_id bytea NOT NULL,
    translation_code_id bytea,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    active smallint DEFAULT '0'::smallint NOT NULL,
    translation_auto_update smallint DEFAULT '1'::smallint NOT NULL
);


--
-- Name: locale; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.locale (
    id bytea NOT NULL,
    code character varying(255) NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: locale_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.locale_translation (
    locale_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    territory character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: log_entry; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.log_entry (
    id bytea NOT NULL,
    message text NOT NULL,
    level smallint NOT NULL,
    channel character varying(255) NOT NULL,
    context jsonb,
    extra jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: mail_header_footer; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mail_header_footer (
    id bytea NOT NULL,
    system_default smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: mail_header_footer_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mail_header_footer_translation (
    mail_header_footer_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    description text,
    header_html text,
    header_plain text,
    footer_html text,
    footer_plain text,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: mail_template; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mail_template (
    id bytea NOT NULL,
    mail_template_type_id bytea,
    system_default smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    was_modified_by_user smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: mail_template_media; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mail_template_media (
    id bytea NOT NULL,
    mail_template_id bytea NOT NULL,
    language_id bytea,
    media_id bytea NOT NULL,
    "position" integer DEFAULT 1 NOT NULL
);


--
-- Name: mail_template_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mail_template_translation (
    mail_template_id bytea NOT NULL,
    language_id bytea NOT NULL,
    sender_name character varying(255),
    subject character varying(255),
    description text,
    content_html text,
    content_plain text,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: mail_template_type; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mail_template_type (
    id bytea NOT NULL,
    technical_name character varying(255) NOT NULL,
    available_entities text,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    template_data text
);


--
-- Name: mail_template_type_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mail_template_type_translation (
    mail_template_type_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: main_category; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.main_category (
    id bytea NOT NULL,
    product_id bytea NOT NULL,
    product_version_id bytea NOT NULL,
    category_id bytea NOT NULL,
    category_version_id bytea NOT NULL,
    sales_channel_id bytea NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: mcp_tool_result_cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mcp_tool_result_cache (
    id bytea NOT NULL,
    session_id character varying(255) NOT NULL,
    mime_type character varying(127) DEFAULT 'application/json'::character varying NOT NULL,
    content text NOT NULL,
    created_at timestamp(3) without time zone NOT NULL
);


--
-- Name: mcp_toolset_session; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mcp_toolset_session (
    session_id character varying(255) NOT NULL,
    toolset_name character varying(255) NOT NULL,
    created_at timestamp(3) without time zone NOT NULL
);


--
-- Name: measurement_display_unit; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.measurement_display_unit (
    id bytea NOT NULL,
    measurement_system_id bytea NOT NULL,
    "default" smallint DEFAULT '0'::smallint NOT NULL,
    type character varying(20) NOT NULL,
    short_name character varying(20) NOT NULL,
    factor double precision NOT NULL,
    "precision" integer DEFAULT 3 NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: measurement_display_unit_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.measurement_display_unit_translation (
    name character varying(255),
    measurement_display_unit_id bytea CONSTRAINT measurement_display_unit_tr_measurement_display_unit_i_not_null NOT NULL,
    language_id bytea NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: measurement_system; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.measurement_system (
    id bytea NOT NULL,
    technical_name character varying(255) NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: measurement_system_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.measurement_system_translation (
    name character varying(255),
    measurement_system_id bytea NOT NULL,
    language_id bytea NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: media; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.media (
    id bytea NOT NULL,
    user_id bytea,
    media_folder_id bytea,
    mime_type character varying(255),
    file_extension character varying(50),
    file_size bigint,
    meta_data jsonb,
    file_name text,
    media_type bytea,
    thumbnails_ro bytea,
    private smallint DEFAULT '0'::smallint NOT NULL,
    uploaded_at timestamp(3) without time zone,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    path character varying(2048),
    config jsonb,
    file_hash character varying(32)
);


--
-- Name: media_default_folder; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.media_default_folder (
    id bytea NOT NULL,
    association_fields jsonb,
    entity character varying(255) NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: media_folder; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.media_folder (
    id bytea NOT NULL,
    parent_id bytea,
    default_folder_id bytea,
    name character varying(255),
    child_count bigint DEFAULT '0'::bigint NOT NULL,
    path text,
    media_folder_configuration_id bytea,
    use_parent_configuration smallint DEFAULT '1'::smallint,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: media_folder_configuration; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.media_folder_configuration (
    id bytea NOT NULL,
    create_thumbnails smallint DEFAULT '1'::smallint,
    thumbnail_quality integer DEFAULT 80,
    media_thumbnail_sizes_ro bytea,
    keep_aspect_ratio smallint DEFAULT '1'::smallint,
    private smallint DEFAULT '0'::smallint,
    no_association smallint,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: media_folder_configuration_media_thumbnail_size; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.media_folder_configuration_media_thumbnail_size (
    media_folder_configuration_id bytea CONSTRAINT media_folder_configuration__media_folder_configuration_not_null NOT NULL,
    media_thumbnail_size_id bytea CONSTRAINT media_folder_configuration_med_media_thumbnail_size_id_not_null NOT NULL
);


--
-- Name: media_tag; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.media_tag (
    media_id bytea NOT NULL,
    tag_id bytea NOT NULL
);


--
-- Name: media_thumbnail; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.media_thumbnail (
    id bytea NOT NULL,
    media_id bytea NOT NULL,
    width bigint NOT NULL,
    height bigint NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    path character varying(2048),
    media_thumbnail_size_id bytea
);


--
-- Name: media_thumbnail_size; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.media_thumbnail_size (
    id bytea NOT NULL,
    width integer NOT NULL,
    height integer NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: media_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.media_translation (
    media_id bytea NOT NULL,
    language_id bytea NOT NULL,
    alt character varying(255),
    title character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: messenger_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messenger_messages (
    id bigint NOT NULL,
    body text NOT NULL,
    headers text NOT NULL,
    queue_name character varying(190) NOT NULL,
    created_at timestamp without time zone NOT NULL,
    available_at timestamp without time zone NOT NULL,
    delivered_at timestamp without time zone
);


--
-- Name: messenger_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.messenger_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: messenger_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.messenger_messages_id_seq OWNED BY public.messenger_messages.id;


--
-- Name: messenger_stats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messenger_stats (
    id bigint NOT NULL,
    message_type character varying(255) NOT NULL,
    time_in_queue integer NOT NULL,
    created_at timestamp without time zone NOT NULL
);


--
-- Name: messenger_stats_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.messenger_stats_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: messenger_stats_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.messenger_stats_id_seq OWNED BY public.messenger_stats.id;


--
-- Name: migration; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migration (
    class character varying(255) NOT NULL,
    creation_timestamp integer NOT NULL,
    update timestamp(6) with time zone,
    update_destructive timestamp(6) with time zone,
    message text
);


--
-- Name: newsletter_recipient; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.newsletter_recipient (
    id bytea NOT NULL,
    email character varying(255) NOT NULL,
    title character varying(255),
    first_name character varying(255),
    last_name character varying(255),
    zip_code character varying(255),
    city character varying(255),
    street character varying(255),
    status character varying(255) NOT NULL,
    hash character varying(255) NOT NULL,
    salutation_id bytea,
    language_id bytea NOT NULL,
    sales_channel_id bytea NOT NULL,
    custom_fields jsonb,
    confirmed_at timestamp(3) without time zone,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: newsletter_recipient_tag; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.newsletter_recipient_tag (
    newsletter_recipient_id bytea NOT NULL,
    tag_id bytea NOT NULL
);


--
-- Name: notification; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notification (
    id bytea NOT NULL,
    status character varying(255) NOT NULL,
    message text NOT NULL,
    admin_only smallint DEFAULT '0'::smallint NOT NULL,
    required_privileges jsonb,
    created_by_integration_id bytea,
    created_by_user_id bytea,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: number_range; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.number_range (
    id bytea NOT NULL,
    type_id bytea NOT NULL,
    global smallint NOT NULL,
    pattern character varying(255) NOT NULL,
    start integer NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: number_range_sales_channel; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.number_range_sales_channel (
    id bytea NOT NULL,
    number_range_id bytea NOT NULL,
    sales_channel_id bytea,
    number_range_type_id bytea NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: number_range_state; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.number_range_state (
    id bytea NOT NULL,
    number_range_id bytea NOT NULL,
    last_value integer NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: number_range_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.number_range_translation (
    number_range_id bytea NOT NULL,
    name character varying(64),
    description character varying(255),
    custom_fields jsonb,
    language_id bytea NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: number_range_type; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.number_range_type (
    id bytea CONSTRAINT number_range_type_id_not_null1 NOT NULL,
    technical_name character varying(64),
    global smallint NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: number_range_type_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.number_range_type_translation (
    number_range_type_id bytea NOT NULL,
    language_id bytea NOT NULL,
    type_name character varying(64),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: oauth_auth_code; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.oauth_auth_code (
    id bytea NOT NULL,
    code_id character varying(80) NOT NULL,
    user_id bytea NOT NULL,
    client_id character varying(255) NOT NULL,
    issued_at timestamp(3) without time zone NOT NULL,
    expires_at timestamp(3) without time zone NOT NULL
);


--
-- Name: oauth_user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.oauth_user (
    id bytea NOT NULL,
    user_id bytea NOT NULL,
    user_sub character varying(255) NOT NULL,
    token jsonb,
    expiry timestamp without time zone NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: order; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public."order" (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    state_id bytea NOT NULL,
    auto_increment bigint NOT NULL,
    order_number character varying(64),
    currency_id bytea NOT NULL,
    language_id bytea NOT NULL,
    currency_factor double precision,
    sales_channel_id bytea NOT NULL,
    billing_address_id bytea NOT NULL,
    billing_address_version_id bytea NOT NULL,
    price jsonb NOT NULL,
    order_date_time timestamp(3) without time zone NOT NULL,
    order_date date,
    amount_total double precision,
    amount_net double precision,
    position_price double precision,
    tax_status character varying(255),
    shipping_costs jsonb NOT NULL,
    shipping_total double precision,
    deep_link_code character varying(32),
    custom_fields jsonb,
    affiliate_code character varying(255),
    campaign_code character varying(255),
    customer_comment text,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    rule_ids jsonb,
    created_by_id bytea,
    updated_by_id bytea,
    item_rounding jsonb,
    total_rounding jsonb,
    source character varying(255),
    tax_calculation_type character varying(50),
    primary_order_delivery_id bytea,
    primary_order_delivery_version_id bytea,
    primary_order_transaction_id bytea,
    primary_order_transaction_version_id bytea,
    internal_comment text,
    CONSTRAINT "char_length.order.deep_link_code" CHECK ((char_length((deep_link_code)::text) = 32))
);


--
-- Name: order_address; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.order_address (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    country_id bytea NOT NULL,
    country_state_id bytea,
    order_id bytea NOT NULL,
    order_version_id bytea NOT NULL,
    company character varying(255),
    department character varying(255),
    salutation_id bytea,
    title character varying(100),
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    street character varying(255) NOT NULL,
    zipcode character varying(50),
    city character varying(70) NOT NULL,
    vat_id character varying(50),
    phone_number character varying(40),
    additional_address_line1 character varying(255),
    additional_address_line2 character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: order_auto_increment_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.order_auto_increment_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: order_auto_increment_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.order_auto_increment_seq OWNED BY public."order".auto_increment;


--
-- Name: order_customer; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.order_customer (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    customer_id bytea,
    order_id bytea NOT NULL,
    order_version_id bytea NOT NULL,
    email character varying(254) NOT NULL,
    salutation_id bytea,
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    title character varying(100),
    vat_ids jsonb,
    company character varying(255),
    customer_number character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    remote_address character varying(255)
);


--
-- Name: order_delivery; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.order_delivery (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    order_id bytea NOT NULL,
    order_version_id bytea NOT NULL,
    state_id bytea NOT NULL,
    shipping_order_address_id bytea,
    shipping_order_address_version_id bytea,
    shipping_method_id bytea NOT NULL,
    tracking_codes jsonb NOT NULL,
    shipping_date_earliest date NOT NULL,
    shipping_date_latest date NOT NULL,
    shipping_costs jsonb NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: order_delivery_position; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.order_delivery_position (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    order_delivery_id bytea NOT NULL,
    order_delivery_version_id bytea NOT NULL,
    order_line_item_id bytea NOT NULL,
    order_line_item_version_id bytea NOT NULL,
    price jsonb NOT NULL,
    total_price double precision,
    unit_price double precision,
    quantity integer,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: order_line_item; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.order_line_item (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    order_id bytea NOT NULL,
    order_version_id bytea NOT NULL,
    parent_id bytea,
    parent_version_id bytea,
    identifier character varying(255) NOT NULL,
    referenced_id character varying(255),
    product_id bytea,
    product_version_id bytea,
    promotion_id bytea,
    label character varying(255) NOT NULL,
    description text,
    cover_id bytea,
    quantity integer NOT NULL,
    unit_price double precision,
    total_price double precision,
    type character varying(255),
    payload jsonb,
    price_definition jsonb,
    price jsonb NOT NULL,
    stackable smallint DEFAULT '1'::smallint NOT NULL,
    removable smallint DEFAULT '1'::smallint NOT NULL,
    good smallint DEFAULT '1'::smallint NOT NULL,
    "position" integer DEFAULT 1 NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    states jsonb
);


--
-- Name: order_line_item_download; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.order_line_item_download (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    "position" integer DEFAULT 1 NOT NULL,
    access_granted smallint DEFAULT '0'::smallint NOT NULL,
    order_line_item_id bytea NOT NULL,
    order_line_item_version_id bytea NOT NULL,
    media_id bytea NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: order_tag; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.order_tag (
    order_id bytea NOT NULL,
    order_version_id bytea NOT NULL,
    tag_id bytea NOT NULL
);


--
-- Name: order_transaction; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.order_transaction (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    order_id bytea NOT NULL,
    order_version_id bytea NOT NULL,
    state_id bytea NOT NULL,
    payment_method_id bytea NOT NULL,
    amount jsonb NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    validation_data jsonb
);


--
-- Name: order_transaction_capture; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.order_transaction_capture (
    id bytea NOT NULL,
    order_transaction_id bytea NOT NULL,
    order_transaction_version_id bytea NOT NULL,
    state_id bytea NOT NULL,
    external_reference character varying(255),
    amount jsonb NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    version_id bytea DEFAULT '\x30783046413931434533453936413442433242453442443943453735324333343235'::bytea NOT NULL
);


--
-- Name: order_transaction_capture_refund; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.order_transaction_capture_refund (
    id bytea NOT NULL,
    capture_id bytea NOT NULL,
    state_id bytea NOT NULL,
    reason character varying(255),
    amount jsonb NOT NULL,
    custom_fields jsonb,
    external_reference character varying(255),
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    version_id bytea DEFAULT '\x30783046413931434533453936413442433242453442443943453735324333343235'::bytea NOT NULL,
    capture_version_id bytea DEFAULT '\x30783046413931434533453936413442433242453442443943453735324333343235'::bytea
);


--
-- Name: order_transaction_capture_refund_position; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.order_transaction_capture_refund_position (
    id bytea NOT NULL,
    refund_id bytea NOT NULL,
    order_line_item_id bytea CONSTRAINT order_transaction_capture_refund_po_order_line_item_id_not_null NOT NULL,
    order_line_item_version_id bytea CONSTRAINT order_transaction_capture_r_order_line_item_version_id_not_null NOT NULL,
    quantity integer NOT NULL,
    reason character varying(255),
    external_reference character varying(255),
    amount jsonb NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    version_id bytea DEFAULT '\x30783046413931434533453936413442433242453442443943453735324333343235'::bytea NOT NULL,
    refund_version_id bytea DEFAULT '\x30783046413931434533453936413442433242453442443943453735324333343235'::bytea
);


--
-- Name: payment_method; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payment_method (
    id bytea NOT NULL,
    handler_identifier character varying(255) DEFAULT 'Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment'::character varying NOT NULL,
    "position" integer DEFAULT 1 NOT NULL,
    active smallint DEFAULT '0'::smallint NOT NULL,
    after_order_enabled smallint DEFAULT '0'::smallint NOT NULL,
    availability_rule_id bytea,
    plugin_id bytea,
    media_id bytea,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    technical_name character varying(255) NOT NULL
);


--
-- Name: payment_method_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payment_method_translation (
    payment_method_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    distinguishable_name character varying(255),
    description text,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: payment_token; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payment_token (
    token character(32) NOT NULL,
    expires timestamp(3) without time zone NOT NULL,
    consumed smallint DEFAULT '0'::smallint
);


--
-- Name: plugin; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.plugin (
    id bytea NOT NULL,
    name character varying(255) NOT NULL,
    base_class character varying(255) NOT NULL,
    composer_name character varying(255),
    active smallint DEFAULT '0'::smallint NOT NULL,
    managed_by_composer smallint DEFAULT '0'::smallint NOT NULL,
    path character varying(255),
    autoload jsonb NOT NULL,
    author character varying(255),
    copyright character varying(255),
    license character varying(255),
    version character varying(255) NOT NULL,
    upgrade_version character varying(255),
    icon bytea,
    installed_at timestamp(3) without time zone,
    upgraded_at timestamp(3) without time zone,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: plugin_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.plugin_translation (
    plugin_id bytea NOT NULL,
    language_id bytea NOT NULL,
    label character varying(255),
    description text,
    manufacturer_link text,
    support_link text,
    changelog jsonb,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: product; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    auto_increment integer NOT NULL,
    product_number character varying(64),
    active smallint,
    parent_id bytea,
    parent_version_id bytea,
    tax_id bytea,
    product_manufacturer_id bytea,
    product_manufacturer_version_id bytea,
    delivery_time_id bytea,
    deliverytime bytea,
    product_media_id bytea,
    product_media_version_id bytea,
    cms_page_id bytea,
    cms_page_version_id bytea DEFAULT '\x30783046413931434533453936413442433242453442443943453735324333343235'::bytea NOT NULL,
    unit_id bytea,
    product_feature_set_id bytea,
    category_tree jsonb,
    category_ids jsonb,
    stream_ids jsonb,
    option_ids jsonb,
    property_ids jsonb,
    tax bytea,
    manufacturer bytea,
    cover bytea,
    unit bytea,
    media bytea,
    prices bytea,
    visibilities bytea,
    properties bytea,
    categories bytea,
    translations bytea,
    price jsonb,
    manufacturer_number character varying(255),
    ean character varying(255),
    sales integer DEFAULT 0 NOT NULL,
    stock integer NOT NULL,
    available_stock integer,
    available smallint DEFAULT '0'::smallint NOT NULL,
    restock_time integer,
    is_closeout smallint,
    purchase_steps bigint,
    max_purchase bigint,
    min_purchase bigint,
    purchase_unit numeric(11,4),
    reference_unit numeric(10,3),
    shipping_free smallint,
    purchase_prices jsonb,
    mark_as_topseller smallint,
    weight numeric(15,6),
    width numeric(10,3),
    height numeric(10,3),
    length numeric(10,3),
    release_date timestamp(3) without time zone,
    tag_ids jsonb,
    tags bytea,
    variant_restrictions jsonb,
    created_at timestamp(3) without time zone,
    updated_at timestamp(3) without time zone,
    rating_average double precision,
    display_group character varying(64),
    child_count integer,
    crosssellings bytea,
    featureset bytea,
    customfieldsets bytea,
    custom_field_set_selection_active smallint,
    canonical_product_id bytea,
    canonical_product_version_id bytea,
    canonicalproduct bytea,
    cmspage bytea,
    cheapest_price text,
    cheapest_price_accessor text,
    states jsonb,
    variant_listing_config jsonb,
    type character varying(32) DEFAULT 'physical'::character varying NOT NULL,
    maincategories bytea,
    open_graph_media_id bytea,
    opengraphmedia bytea,
    guarantee_confirmed smallint,
    guarantee_months integer
);


--
-- Name: product_auto_increment_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.product_auto_increment_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: product_auto_increment_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.product_auto_increment_seq OWNED BY public.product.auto_increment;


--
-- Name: product_category; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_category (
    product_id bytea NOT NULL,
    product_version_id bytea NOT NULL,
    category_id bytea NOT NULL,
    category_version_id bytea NOT NULL
);


--
-- Name: product_category_tree; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_category_tree (
    product_id bytea NOT NULL,
    product_version_id bytea NOT NULL,
    category_id bytea NOT NULL,
    category_version_id bytea NOT NULL
);


--
-- Name: product_configurator_setting; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_configurator_setting (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    product_id bytea NOT NULL,
    product_version_id bytea NOT NULL,
    property_group_option_id bytea NOT NULL,
    price jsonb,
    "position" integer DEFAULT 0 NOT NULL,
    media_id bytea,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: product_cross_selling; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_cross_selling (
    id bytea NOT NULL,
    type character varying(255) NOT NULL,
    "position" integer NOT NULL,
    sort_by character varying(255) NOT NULL,
    sort_direction character varying(255) NOT NULL,
    active smallint DEFAULT '0'::smallint,
    "limit" integer DEFAULT 24 NOT NULL,
    product_id bytea NOT NULL,
    product_version_id bytea NOT NULL,
    product_stream_id bytea,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: product_cross_selling_assigned_products; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_cross_selling_assigned_products (
    id bytea NOT NULL,
    cross_selling_id bytea CONSTRAINT product_cross_selling_assigned_produc_cross_selling_id_not_null NOT NULL,
    product_id bytea NOT NULL,
    product_version_id bytea CONSTRAINT product_cross_selling_assigned_prod_product_version_id_not_null NOT NULL,
    "position" integer DEFAULT 0 NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: product_cross_selling_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_cross_selling_translation (
    product_cross_selling_id bytea CONSTRAINT product_cross_selling_transla_product_cross_selling_id_not_null NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: product_custom_field_set; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_custom_field_set (
    custom_field_set_id bytea NOT NULL,
    product_id bytea NOT NULL,
    product_version_id bytea NOT NULL
);


--
-- Name: product_download; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_download (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    "position" integer DEFAULT 1 NOT NULL,
    product_id bytea NOT NULL,
    product_version_id bytea NOT NULL,
    media_id bytea NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: product_export; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_export (
    id bytea NOT NULL,
    product_stream_id bytea NOT NULL,
    storefront_sales_channel_id bytea,
    sales_channel_id bytea NOT NULL,
    sales_channel_domain_id bytea,
    file_name character varying(255) NOT NULL,
    access_key character varying(255) NOT NULL,
    encoding character varying(255) NOT NULL,
    file_format character varying(255) NOT NULL,
    include_variants smallint DEFAULT '0'::smallint,
    generate_by_cronjob smallint DEFAULT '0'::smallint NOT NULL,
    generated_at timestamp(3) without time zone,
    "interval" integer NOT NULL,
    header_template text,
    body_template text,
    footer_template text,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    currency_id bytea NOT NULL,
    paused_schedule smallint DEFAULT '0'::smallint,
    is_running smallint DEFAULT '0'::smallint NOT NULL,
    provider character varying(255),
    next_generation_at timestamp(3) without time zone,
    feed_label character varying(20)
);


--
-- Name: product_feature_set; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_feature_set (
    id bytea NOT NULL,
    features jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: product_feature_set_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_feature_set_translation (
    product_feature_set_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    description text,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: product_keyword_dictionary; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_keyword_dictionary (
    id bytea NOT NULL,
    language_id bytea NOT NULL,
    keyword character varying(500) NOT NULL,
    reversed character varying(500)
);


--
-- Name: product_manufacturer; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_manufacturer (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    link character varying(255),
    media_id bytea,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: product_manufacturer_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_manufacturer_translation (
    product_manufacturer_id bytea CONSTRAINT product_manufacturer_translati_product_manufacturer_id_not_null NOT NULL,
    product_manufacturer_version_id bytea CONSTRAINT product_manufacturer_transl_product_manufacturer_versi_not_null NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    description text,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    link text
);


--
-- Name: product_media; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_media (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    "position" integer DEFAULT 1 NOT NULL,
    product_id bytea NOT NULL,
    product_version_id bytea NOT NULL,
    media_id bytea NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: product_option; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_option (
    product_id bytea NOT NULL,
    product_version_id bytea NOT NULL,
    property_group_option_id bytea NOT NULL
);


--
-- Name: product_price; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_price (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    rule_id bytea NOT NULL,
    product_id bytea NOT NULL,
    product_version_id bytea NOT NULL,
    price jsonb NOT NULL,
    quantity_start integer NOT NULL,
    quantity_end integer,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: product_property; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_property (
    product_id bytea NOT NULL,
    product_version_id bytea NOT NULL,
    property_group_option_id bytea NOT NULL
);


--
-- Name: product_review; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_review (
    id bytea NOT NULL,
    product_id bytea NOT NULL,
    customer_id bytea,
    sales_channel_id bytea,
    language_id bytea,
    external_user character varying(255),
    external_email character varying(255),
    title character varying(255),
    content text,
    points double precision,
    status smallint DEFAULT '0'::smallint,
    comment text,
    custom_fields jsonb,
    updated_at timestamp(3) without time zone,
    created_at timestamp(3) without time zone NOT NULL,
    product_version_id bytea NOT NULL
);


--
-- Name: product_search_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_search_config (
    id bytea NOT NULL,
    language_id bytea NOT NULL,
    and_logic smallint DEFAULT '1'::smallint NOT NULL,
    min_search_length smallint DEFAULT '2'::smallint NOT NULL,
    excluded_terms jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: product_search_config_field; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_search_config_field (
    id bytea NOT NULL,
    product_search_config_id bytea NOT NULL,
    custom_field_id bytea,
    field character varying(255) NOT NULL,
    tokenize smallint DEFAULT '0'::smallint NOT NULL,
    searchable smallint DEFAULT '0'::smallint NOT NULL,
    ranking integer DEFAULT 0 NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    use_exact_subfield smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: product_search_keyword; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_search_keyword (
    id bytea NOT NULL,
    version_id bytea NOT NULL,
    language_id bytea NOT NULL,
    product_id bytea NOT NULL,
    product_version_id bytea NOT NULL,
    keyword character varying(255) NOT NULL,
    ranking double precision NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: product_sorting; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_sorting (
    id bytea NOT NULL,
    url_key character varying(255) NOT NULL,
    priority bigint NOT NULL,
    active smallint DEFAULT '1'::smallint NOT NULL,
    fields jsonb NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    locked smallint DEFAULT '0'::smallint NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: product_sorting_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_sorting_translation (
    product_sorting_id bytea NOT NULL,
    language_id bytea NOT NULL,
    label character varying(255),
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: product_stream; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_stream (
    id bytea NOT NULL,
    api_filter jsonb,
    invalid smallint DEFAULT '1'::smallint NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    internal smallint DEFAULT '0'::smallint NOT NULL,
    display_as_group smallint DEFAULT '1'::smallint NOT NULL
);


--
-- Name: product_stream_filter; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_stream_filter (
    id bytea NOT NULL,
    product_stream_id bytea NOT NULL,
    parent_id bytea,
    type character varying(255) NOT NULL,
    field character varying(255),
    operator character varying(255),
    value text,
    parameters text,
    "position" integer DEFAULT 0 NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: product_stream_mapping; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_stream_mapping (
    product_id bytea NOT NULL,
    product_version_id bytea NOT NULL,
    product_stream_id bytea NOT NULL
);


--
-- Name: product_stream_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_stream_translation (
    product_stream_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    description text,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: product_tag; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_tag (
    product_id bytea NOT NULL,
    product_version_id bytea NOT NULL,
    tag_id bytea NOT NULL
);


--
-- Name: product_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_translation (
    product_id bytea NOT NULL,
    product_version_id bytea NOT NULL,
    language_id bytea NOT NULL,
    meta_description character varying(255),
    name character varying(255),
    keywords text,
    description text,
    meta_title character varying(255),
    pack_unit character varying(255),
    custom_fields jsonb,
    slot_config jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    pack_unit_plural character varying(255),
    custom_search_keywords jsonb,
    og_title character varying(255),
    og_description character varying(255),
    description_teaser character varying(512)
);


--
-- Name: product_visibility; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_visibility (
    id bytea NOT NULL,
    product_id bytea NOT NULL,
    product_version_id bytea NOT NULL,
    sales_channel_id bytea NOT NULL,
    visibility integer NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: promotion; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.promotion (
    id bytea NOT NULL,
    active smallint DEFAULT '0'::smallint NOT NULL,
    valid_from timestamp without time zone,
    valid_until timestamp without time zone,
    max_redemptions_global integer,
    max_redemptions_per_customer integer,
    priority integer DEFAULT 1 NOT NULL,
    order_count integer DEFAULT 0 NOT NULL,
    orders_per_customer_count jsonb,
    exclusive smallint DEFAULT '0'::smallint NOT NULL,
    code character varying(255),
    use_codes smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    customer_restriction smallint DEFAULT '0'::smallint NOT NULL,
    prevent_combination smallint DEFAULT '0'::smallint NOT NULL,
    exclusion_ids jsonb,
    use_individual_codes smallint DEFAULT '0'::smallint NOT NULL,
    individual_code_pattern character varying(255),
    use_setgroups smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: promotion_cart_rule; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.promotion_cart_rule (
    promotion_id bytea NOT NULL,
    rule_id bytea NOT NULL
);


--
-- Name: promotion_discount; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.promotion_discount (
    id bytea NOT NULL,
    promotion_id bytea NOT NULL,
    scope character varying(32) NOT NULL,
    type character varying(32) NOT NULL,
    value double precision NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    consider_advanced_rules smallint DEFAULT '0'::smallint NOT NULL,
    max_value double precision,
    sorter_key character varying(255),
    applier_key character varying(255),
    usage_key character varying(255),
    picker_key character varying(255)
);


--
-- Name: promotion_discount_prices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.promotion_discount_prices (
    id bytea NOT NULL,
    discount_id bytea NOT NULL,
    currency_id bytea NOT NULL,
    price double precision DEFAULT '0'::double precision NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: promotion_discount_rule; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.promotion_discount_rule (
    discount_id bytea NOT NULL,
    rule_id bytea NOT NULL
);


--
-- Name: promotion_individual_code; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.promotion_individual_code (
    id bytea NOT NULL,
    promotion_id bytea NOT NULL,
    code character varying(255),
    payload jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: promotion_order_rule; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.promotion_order_rule (
    promotion_id bytea NOT NULL,
    rule_id bytea NOT NULL
);


--
-- Name: promotion_persona_customer; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.promotion_persona_customer (
    promotion_id bytea NOT NULL,
    customer_id bytea NOT NULL
);


--
-- Name: promotion_persona_rule; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.promotion_persona_rule (
    promotion_id bytea NOT NULL,
    rule_id bytea NOT NULL
);


--
-- Name: promotion_sales_channel; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.promotion_sales_channel (
    id bytea NOT NULL,
    promotion_id bytea NOT NULL,
    sales_channel_id bytea NOT NULL,
    priority integer DEFAULT 0 NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: promotion_setgroup; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.promotion_setgroup (
    id bytea NOT NULL,
    promotion_id bytea NOT NULL,
    packager_key character varying(255) NOT NULL,
    sorter_key character varying(255) NOT NULL,
    value double precision NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: promotion_setgroup_rule; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.promotion_setgroup_rule (
    setgroup_id bytea NOT NULL,
    rule_id bytea NOT NULL
);


--
-- Name: promotion_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.promotion_translation (
    name character varying(255),
    promotion_id bytea NOT NULL,
    language_id bytea NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: property_group; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.property_group (
    id bytea NOT NULL,
    sorting_type character varying(50) DEFAULT 'alphanumeric'::character varying NOT NULL,
    display_type character varying(50) DEFAULT 'text'::character varying NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    filterable smallint DEFAULT '1'::smallint NOT NULL,
    visible_on_product_detail_page smallint DEFAULT '1'::smallint
);


--
-- Name: property_group_option; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.property_group_option (
    id bytea NOT NULL,
    property_group_id bytea NOT NULL,
    color_hex_code character varying(20),
    media_id bytea,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: property_group_option_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.property_group_option_translation (
    property_group_option_id bytea CONSTRAINT property_group_option_transla_property_group_option_id_not_null NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    "position" integer DEFAULT 1 NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: property_group_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.property_group_translation (
    property_group_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    description text,
    "position" integer DEFAULT 1,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: refresh_token; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.refresh_token (
    id bytea NOT NULL,
    user_id bytea NOT NULL,
    token_id character varying(80) NOT NULL,
    issued_at timestamp(3) without time zone NOT NULL,
    expires_at timestamp(3) without time zone NOT NULL,
    family_id bytea,
    revoked_at timestamp(3) without time zone
);


--
-- Name: rule; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rule (
    id bytea NOT NULL,
    name character varying(500) NOT NULL,
    description text,
    priority integer NOT NULL,
    payload bytea,
    invalid smallint DEFAULT '0'::smallint NOT NULL,
    areas jsonb,
    module_types jsonb,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: rule_condition; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rule_condition (
    id bytea NOT NULL,
    type character varying(255) NOT NULL,
    rule_id bytea NOT NULL,
    script_id bytea,
    parent_id bytea,
    value jsonb,
    "position" integer DEFAULT 0 NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: rule_tag; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rule_tag (
    rule_id bytea NOT NULL,
    tag_id bytea NOT NULL
);


--
-- Name: sales_channel; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_channel (
    id bytea NOT NULL,
    type_id bytea NOT NULL,
    short_name character varying(45),
    configuration jsonb,
    access_key character varying(255) NOT NULL,
    language_id bytea NOT NULL,
    currency_id bytea NOT NULL,
    payment_method_id bytea NOT NULL,
    shipping_method_id bytea NOT NULL,
    country_id bytea NOT NULL,
    navigation_category_id bytea NOT NULL,
    navigation_category_version_id bytea NOT NULL,
    navigation_category_depth integer DEFAULT 2 NOT NULL,
    hreflang_active smallint DEFAULT '0'::smallint,
    hreflang_default_domain_id bytea,
    footer_category_id bytea,
    footer_category_version_id bytea,
    service_category_id bytea,
    service_category_version_id bytea,
    active smallint DEFAULT '1'::smallint NOT NULL,
    maintenance smallint DEFAULT '0'::smallint NOT NULL,
    maintenance_ip_whitelist jsonb,
    customer_group_id bytea NOT NULL,
    mail_header_footer_id bytea,
    payment_method_ids jsonb,
    analytics_id bytea,
    tax_calculation_type character varying(50) DEFAULT 'horizontal'::character varying NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    home_cms_page_id bytea,
    home_cms_page_version_id bytea,
    measurement_units jsonb,
    maintenance_ip_allowlist jsonb,
    business_time_zone character varying(255)
);


--
-- Name: sales_channel_analytics; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_channel_analytics (
    id bytea NOT NULL,
    tracking_id character varying(50) NOT NULL,
    active smallint DEFAULT '0'::smallint NOT NULL,
    track_orders smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    anonymize_ip smallint DEFAULT '1'::smallint NOT NULL,
    track_offcanvas_cart smallint DEFAULT '0'::smallint NOT NULL,
    enhanced_conversions smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: sales_channel_api_context; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_channel_api_context (
    token character varying(255) NOT NULL,
    payload jsonb NOT NULL,
    sales_channel_id bytea,
    customer_id bytea,
    updated_at timestamp with time zone
);


--
-- Name: sales_channel_country; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_channel_country (
    sales_channel_id bytea NOT NULL,
    country_id bytea NOT NULL
);


--
-- Name: sales_channel_currency; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_channel_currency (
    sales_channel_id bytea NOT NULL,
    currency_id bytea NOT NULL
);


--
-- Name: sales_channel_domain; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_channel_domain (
    id bytea NOT NULL,
    sales_channel_id bytea NOT NULL,
    language_id bytea NOT NULL,
    url character varying(255) NOT NULL,
    currency_id bytea NOT NULL,
    snippet_set_id bytea NOT NULL,
    hreflang_use_only_locale smallint DEFAULT '0'::smallint,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    measurement_units jsonb,
    is_external_storefront smallint DEFAULT '0'::smallint NOT NULL,
    external_storefront_language_id bytea
);


--
-- Name: sales_channel_file; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_channel_file (
    id bytea NOT NULL,
    sales_channel_id bytea NOT NULL,
    file_family character varying(64) NOT NULL,
    file_name character varying(512) NOT NULL,
    enabled smallint DEFAULT '0'::smallint NOT NULL,
    template_overrides jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: sales_channel_language; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_channel_language (
    sales_channel_id bytea NOT NULL,
    language_id bytea NOT NULL
);


--
-- Name: sales_channel_payment_method; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_channel_payment_method (
    sales_channel_id bytea NOT NULL,
    payment_method_id bytea NOT NULL
);


--
-- Name: sales_channel_shipping_method; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_channel_shipping_method (
    sales_channel_id bytea NOT NULL,
    shipping_method_id bytea NOT NULL
);


--
-- Name: sales_channel_tracking_customer; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_channel_tracking_customer (
    id bytea NOT NULL,
    customer_id bytea NOT NULL,
    sales_channel_id bytea NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: sales_channel_tracking_order; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_channel_tracking_order (
    id bytea NOT NULL,
    order_id bytea NOT NULL,
    order_version_id bytea NOT NULL,
    sales_channel_id bytea NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: sales_channel_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_channel_translation (
    sales_channel_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    home_keywords character varying(255),
    home_meta_description character varying(255),
    home_meta_title character varying(255),
    home_name character varying(255),
    home_enabled smallint DEFAULT '1'::smallint NOT NULL,
    home_slot_config jsonb,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: sales_channel_type; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_channel_type (
    id bytea CONSTRAINT sales_channel_type_id_not_null1 NOT NULL,
    cover_url character varying(500),
    icon_name character varying(255),
    screenshot_urls jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: sales_channel_type_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_channel_type_translation (
    sales_channel_type_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    manufacturer character varying(255),
    description character varying(255),
    description_long text,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: salutation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.salutation (
    id bytea NOT NULL,
    salutation_key character varying(255) NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    "position" integer DEFAULT 100 NOT NULL
);


--
-- Name: salutation_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.salutation_translation (
    salutation_id bytea NOT NULL,
    language_id bytea NOT NULL,
    display_name character varying(255),
    letter_name character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: scheduled_task; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.scheduled_task (
    id bytea NOT NULL,
    name character varying(255) NOT NULL,
    scheduled_task_class character varying(512) NOT NULL,
    run_interval integer NOT NULL,
    status character varying(255) NOT NULL,
    last_execution_time timestamp(3) without time zone,
    next_execution_time timestamp(3) without time zone NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    default_run_interval integer NOT NULL,
    CONSTRAINT "check.scheduled_task.run_interval" CHECK ((run_interval >= 1))
);


--
-- Name: script; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.script (
    id bytea NOT NULL,
    script text NOT NULL,
    hook character varying(255) NOT NULL,
    name character varying(1024) NOT NULL,
    active smallint NOT NULL,
    app_id bytea,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: seo_url; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.seo_url (
    id bytea NOT NULL,
    language_id bytea NOT NULL,
    sales_channel_id bytea,
    foreign_key bytea NOT NULL,
    route_name character varying(50) NOT NULL,
    path_info character varying(750) NOT NULL,
    seo_path_info character varying(750) NOT NULL,
    is_canonical smallint,
    is_modified smallint DEFAULT '0'::smallint NOT NULL,
    is_deleted smallint DEFAULT '0'::smallint NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: seo_url_template; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.seo_url_template (
    id bytea NOT NULL,
    sales_channel_id bytea,
    route_name character varying(255) NOT NULL,
    entity_name character varying(64) NOT NULL,
    template character varying(750),
    is_valid smallint DEFAULT '1'::smallint NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    is_headless smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: shipping_method; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.shipping_method (
    id bytea NOT NULL,
    active smallint DEFAULT '0'::smallint NOT NULL,
    "position" integer DEFAULT 1 NOT NULL,
    availability_rule_id bytea,
    media_id bytea,
    delivery_time_id bytea NOT NULL,
    tax_type character varying(50) DEFAULT 'auto'::character varying,
    tax_id bytea,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    technical_name character varying(255) NOT NULL
);


--
-- Name: shipping_method_price; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.shipping_method_price (
    id bytea NOT NULL,
    shipping_method_id bytea NOT NULL,
    calculation bigint,
    rule_id bytea,
    calculation_rule_id bytea,
    currency_price jsonb,
    quantity_start double precision,
    quantity_end double precision,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: shipping_method_tag; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.shipping_method_tag (
    shipping_method_id bytea NOT NULL,
    tag_id bytea NOT NULL
);


--
-- Name: shipping_method_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.shipping_method_translation (
    shipping_method_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    description text,
    tracking_url text,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: snippet; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.snippet (
    id bytea NOT NULL,
    translation_key character varying(255) NOT NULL,
    value text NOT NULL,
    author character varying(255) NOT NULL,
    snippet_set_id bytea NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: snippet_set; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.snippet_set (
    id bytea NOT NULL,
    name character varying(255) NOT NULL,
    base_file character varying(255) NOT NULL,
    iso character varying(255) NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: state_machine; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.state_machine (
    id bytea NOT NULL,
    technical_name character varying(255) NOT NULL,
    initial_state_id bytea,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: state_machine_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.state_machine_history (
    id bytea NOT NULL,
    state_machine_id bytea NOT NULL,
    entity_name character varying(100) NOT NULL,
    from_state_id bytea NOT NULL,
    to_state_id bytea NOT NULL,
    action_name character varying(255) NOT NULL,
    user_id bytea,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    referenced_id bytea NOT NULL,
    referenced_version_id bytea NOT NULL,
    integration_id bytea,
    internal_comment text,
    source_type character varying(32)
);


--
-- Name: state_machine_state; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.state_machine_state (
    id bytea NOT NULL,
    technical_name character varying(255) NOT NULL,
    state_machine_id bytea NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: state_machine_state_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.state_machine_state_translation (
    language_id bytea NOT NULL,
    state_machine_state_id bytea NOT NULL,
    name character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: state_machine_transition; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.state_machine_transition (
    id bytea NOT NULL,
    action_name character varying(255) NOT NULL,
    state_machine_id bytea NOT NULL,
    from_state_id bytea NOT NULL,
    to_state_id bytea NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: state_machine_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.state_machine_translation (
    language_id bytea NOT NULL,
    state_machine_id bytea NOT NULL,
    name character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: system_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.system_config (
    id bytea NOT NULL,
    configuration_key character varying(255) NOT NULL,
    configuration_value jsonb NOT NULL,
    sales_channel_id bytea,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: tag; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tag (
    id bytea NOT NULL,
    name character varying(255) NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: tax; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tax (
    id bytea NOT NULL,
    tax_rate numeric(10,3),
    name character varying(255) NOT NULL,
    "position" integer DEFAULT 0 NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: tax_provider; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tax_provider (
    id bytea NOT NULL,
    active smallint DEFAULT '1'::smallint NOT NULL,
    priority integer DEFAULT 1 NOT NULL,
    identifier character varying(255) NOT NULL,
    availability_rule_id bytea,
    app_id bytea,
    process_url character varying(255),
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: tax_provider_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tax_provider_translation (
    tax_provider_id bytea NOT NULL,
    language_id bytea NOT NULL,
    name character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: tax_rule; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tax_rule (
    id bytea NOT NULL,
    tax_id bytea NOT NULL,
    tax_rule_type_id bytea NOT NULL,
    country_id bytea NOT NULL,
    tax_rate double precision,
    data jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    active_from timestamp(3) without time zone
);


--
-- Name: tax_rule_type; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tax_rule_type (
    id bytea NOT NULL,
    technical_name character varying(255) NOT NULL,
    "position" integer NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: tax_rule_type_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tax_rule_type_translation (
    tax_rule_type_id bytea NOT NULL,
    language_id bytea NOT NULL,
    type_name character varying(255),
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: theme; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.theme (
    id bytea NOT NULL,
    technical_name character varying(255),
    name character varying(255) NOT NULL,
    author character varying(255) NOT NULL,
    preview_media_id bytea,
    parent_theme_id bytea,
    base_config jsonb,
    config_values jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    active smallint DEFAULT '1'::smallint,
    theme_json jsonb
);


--
-- Name: theme_child; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.theme_child (
    parent_id bytea NOT NULL,
    child_id bytea NOT NULL
);


--
-- Name: theme_media; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.theme_media (
    theme_id bytea NOT NULL,
    media_id bytea NOT NULL
);


--
-- Name: theme_runtime_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.theme_runtime_config (
    theme_id bytea NOT NULL,
    technical_name character varying(255),
    resolved_config jsonb NOT NULL,
    view_inheritance jsonb NOT NULL,
    script_files jsonb,
    icon_sets jsonb NOT NULL,
    updated_at timestamp(3) without time zone NOT NULL,
    import_map jsonb
);


--
-- Name: theme_sales_channel; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.theme_sales_channel (
    theme_id bytea NOT NULL,
    sales_channel_id bytea NOT NULL
);


--
-- Name: theme_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.theme_translation (
    theme_id bytea NOT NULL,
    language_id bytea NOT NULL,
    description text,
    labels jsonb,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    help_texts jsonb
);


--
-- Name: unit; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.unit (
    id bytea NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: unit_translation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.unit_translation (
    unit_id bytea NOT NULL,
    language_id bytea NOT NULL,
    short_code character varying(255),
    name character varying(255),
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: usage_data_entity_deletion; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usage_data_entity_deletion (
    id bytea NOT NULL,
    entity_ids jsonb NOT NULL,
    entity_name character varying(255) NOT NULL,
    deleted_at timestamp(3) without time zone NOT NULL
);


--
-- Name: user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public."user" (
    id bytea NOT NULL,
    username character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    title character varying(255),
    email character varying(255) NOT NULL,
    active smallint DEFAULT '1'::smallint NOT NULL,
    admin smallint,
    avatar_id bytea,
    locale_id bytea NOT NULL,
    store_token character varying(255),
    last_updated_password_at timestamp(3) without time zone,
    time_zone character varying(255) DEFAULT 'UTC'::character varying NOT NULL,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    mcp_allowlist jsonb
);


--
-- Name: user_access_key; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_access_key (
    id bytea NOT NULL,
    user_id bytea NOT NULL,
    access_key character varying(255) NOT NULL,
    secret_access_key character varying(255) NOT NULL,
    last_usage_at timestamp(3) without time zone,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: user_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_config (
    id bytea NOT NULL,
    user_id bytea NOT NULL,
    key character varying(255) NOT NULL,
    value jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: user_recovery; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_recovery (
    id bytea NOT NULL,
    user_id bytea NOT NULL,
    hash character varying(255) NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: version; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version (
    id bytea NOT NULL,
    name character varying(255) NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: version_commit; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_commit (
    id bytea NOT NULL,
    auto_increment bigint NOT NULL,
    is_merge smallint DEFAULT '0'::smallint NOT NULL,
    message character varying(5000),
    user_id bytea,
    integration_id bytea,
    version_id bytea NOT NULL,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: version_commit_auto_increment_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_commit_auto_increment_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_commit_auto_increment_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_commit_auto_increment_seq OWNED BY public.version_commit.auto_increment;


--
-- Name: version_commit_data; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_commit_data (
    id bytea NOT NULL,
    auto_increment bigint NOT NULL,
    version_commit_id bytea NOT NULL,
    entity_name character varying(100) NOT NULL,
    entity_id jsonb NOT NULL,
    action character varying(100) NOT NULL,
    payload jsonb NOT NULL,
    user_id bytea,
    integration_id bytea,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: version_commit_data_auto_increment_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_commit_data_auto_increment_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_commit_data_auto_increment_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_commit_data_auto_increment_seq OWNED BY public.version_commit_data.auto_increment;


--
-- Name: webhook; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.webhook (
    id bytea NOT NULL,
    name character varying(255) NOT NULL,
    event_name character varying(500) NOT NULL,
    url character varying(500) NOT NULL,
    app_id bytea,
    active smallint DEFAULT '1'::smallint,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    error_count integer DEFAULT 0 NOT NULL,
    only_live_version smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: webhook_delivery; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.webhook_delivery (
    id bigint NOT NULL,
    webhook_event_log_id bytea NOT NULL,
    webhook_id bytea,
    partition_key bytea NOT NULL,
    delivery_status character varying(20) DEFAULT 'queued'::character varying NOT NULL,
    execution_count bigint DEFAULT '0'::bigint NOT NULL,
    next_retry_at timestamp(3) without time zone,
    last_attempt_at timestamp(3) without time zone,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone
);


--
-- Name: webhook_delivery_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.webhook_delivery_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: webhook_delivery_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.webhook_delivery_id_seq OWNED BY public.webhook_delivery.id;


--
-- Name: webhook_event_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.webhook_event_log (
    id bytea NOT NULL,
    app_name character varying(255),
    webhook_name text NOT NULL,
    event_name character varying(255) NOT NULL,
    delivery_status character varying(255) NOT NULL,
    "timestamp" integer,
    processing_time integer,
    app_version character varying(255),
    request_content jsonb,
    response_content jsonb,
    response_status_code integer,
    response_reason_phrase character varying(255),
    url character varying(500) NOT NULL,
    serialized_webhook_message bytea,
    custom_fields jsonb,
    created_at timestamp(3) without time zone NOT NULL,
    updated_at timestamp(3) without time zone,
    only_live_version smallint DEFAULT '0'::smallint NOT NULL,
    sequence bigint
);


--
-- Name: webhook_stream; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.webhook_stream (
    id bytea NOT NULL,
    partition_key bytea NOT NULL,
    locked_by character varying(64),
    lock_expires_at timestamp(3) without time zone,
    last_claimed_at timestamp(3) without time zone,
    created_at timestamp(3) without time zone NOT NULL
);


--
-- Name: cart auto_increment; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cart ALTER COLUMN auto_increment SET DEFAULT nextval('public.cart_auto_increment_seq'::regclass);


--
-- Name: category auto_increment; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category ALTER COLUMN auto_increment SET DEFAULT nextval('public.category_auto_increment_seq'::regclass);


--
-- Name: consent_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consent_log ALTER COLUMN id SET DEFAULT nextval('public.consent_log_id_seq'::regclass);


--
-- Name: customer auto_increment; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer ALTER COLUMN auto_increment SET DEFAULT nextval('public.customer_auto_increment_seq'::regclass);


--
-- Name: messenger_messages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messenger_messages ALTER COLUMN id SET DEFAULT nextval('public.messenger_messages_id_seq'::regclass);


--
-- Name: messenger_stats id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messenger_stats ALTER COLUMN id SET DEFAULT nextval('public.messenger_stats_id_seq'::regclass);


--
-- Name: order auto_increment; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public."order" ALTER COLUMN auto_increment SET DEFAULT nextval('public.order_auto_increment_seq'::regclass);


--
-- Name: product auto_increment; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product ALTER COLUMN auto_increment SET DEFAULT nextval('public.product_auto_increment_seq'::regclass);


--
-- Name: version_commit auto_increment; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_commit ALTER COLUMN auto_increment SET DEFAULT nextval('public.version_commit_auto_increment_seq'::regclass);


--
-- Name: version_commit_data auto_increment; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_commit_data ALTER COLUMN auto_increment SET DEFAULT nextval('public.version_commit_data_auto_increment_seq'::regclass);


--
-- Name: webhook_delivery id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_delivery ALTER COLUMN id SET DEFAULT nextval('public.webhook_delivery_id_seq'::regclass);


--
-- PostgreSQL database dump complete
--
