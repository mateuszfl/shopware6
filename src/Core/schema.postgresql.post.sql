--
-- PostgreSQL database dump
--


-- Dumped from database version 18.6 (Debian 18.6-1.pgdg13+2)
-- Dumped by pg_dump version 18.6 (Debian 18.6-1.pgdg13+2)



--
-- Name: acl_role idx_16575229_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.acl_role
    ADD CONSTRAINT idx_16575229_primary PRIMARY KEY (id);


--
-- Name: acl_user_role idx_16575238_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.acl_user_role
    ADD CONSTRAINT idx_16575238_primary PRIMARY KEY (user_id, acl_role_id);


--
-- Name: admin_elasticsearch_index_task idx_16575246_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.admin_elasticsearch_index_task
    ADD CONSTRAINT idx_16575246_primary PRIMARY KEY (id);


--
-- Name: app idx_16575256_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app
    ADD CONSTRAINT idx_16575256_primary PRIMARY KEY (id);


--
-- Name: app_action_button idx_16575282_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_action_button
    ADD CONSTRAINT idx_16575282_primary PRIMARY KEY (id);


--
-- Name: app_action_button_translation idx_16575294_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_action_button_translation
    ADD CONSTRAINT idx_16575294_primary PRIMARY KEY (app_action_button_id, language_id);


--
-- Name: app_administration_snippet idx_16575302_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_administration_snippet
    ADD CONSTRAINT idx_16575302_primary PRIMARY KEY (id);


--
-- Name: app_cms_block idx_16575312_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_cms_block
    ADD CONSTRAINT idx_16575312_primary PRIMARY KEY (id);


--
-- Name: app_cms_block_translation idx_16575324_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_cms_block_translation
    ADD CONSTRAINT idx_16575324_primary PRIMARY KEY (app_cms_block_id, language_id);


--
-- Name: app_config idx_16575332_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_config
    ADD CONSTRAINT idx_16575332_primary PRIMARY KEY (key);


--
-- Name: app_feature idx_16575339_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_feature
    ADD CONSTRAINT idx_16575339_primary PRIMARY KEY (id);


--
-- Name: app_flow_action idx_16575350_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_flow_action
    ADD CONSTRAINT idx_16575350_primary PRIMARY KEY (id);


--
-- Name: app_flow_action_translation idx_16575362_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_flow_action_translation
    ADD CONSTRAINT idx_16575362_primary PRIMARY KEY (app_flow_action_id, language_id);


--
-- Name: app_flow_event idx_16575370_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_flow_event
    ADD CONSTRAINT idx_16575370_primary PRIMARY KEY (id);


--
-- Name: app_mcp_prompt idx_16575380_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_mcp_prompt
    ADD CONSTRAINT idx_16575380_primary PRIMARY KEY (id);


--
-- Name: app_mcp_prompt_translation idx_16575390_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_mcp_prompt_translation
    ADD CONSTRAINT idx_16575390_primary PRIMARY KEY (app_mcp_prompt_id, language_id);


--
-- Name: app_mcp_resource idx_16575398_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_mcp_resource
    ADD CONSTRAINT idx_16575398_primary PRIMARY KEY (id);


--
-- Name: app_mcp_resource_translation idx_16575409_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_mcp_resource_translation
    ADD CONSTRAINT idx_16575409_primary PRIMARY KEY (app_mcp_resource_id, language_id);


--
-- Name: app_mcp_tool idx_16575417_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_mcp_tool
    ADD CONSTRAINT idx_16575417_primary PRIMARY KEY (id);


--
-- Name: app_mcp_tool_translation idx_16575427_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_mcp_tool_translation
    ADD CONSTRAINT idx_16575427_primary PRIMARY KEY (app_mcp_tool_id, language_id);


--
-- Name: app_payment_method idx_16575435_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_payment_method
    ADD CONSTRAINT idx_16575435_primary PRIMARY KEY (id);


--
-- Name: app_script_condition idx_16575445_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_script_condition
    ADD CONSTRAINT idx_16575445_primary PRIMARY KEY (id);


--
-- Name: app_script_condition_translation idx_16575456_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_script_condition_translation
    ADD CONSTRAINT idx_16575456_primary PRIMARY KEY (app_script_condition_id, language_id);


--
-- Name: app_shipping_method idx_16575464_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_shipping_method
    ADD CONSTRAINT idx_16575464_primary PRIMARY KEY (id);


--
-- Name: app_template idx_16575474_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_template
    ADD CONSTRAINT idx_16575474_primary PRIMARY KEY (id);


--
-- Name: app_translation idx_16575485_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_translation
    ADD CONSTRAINT idx_16575485_primary PRIMARY KEY (app_id, language_id);


--
-- Name: cart idx_16575494_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cart
    ADD CONSTRAINT idx_16575494_primary PRIMARY KEY (token);


--
-- Name: category idx_16575507_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category
    ADD CONSTRAINT idx_16575507_primary PRIMARY KEY (id, version_id);


--
-- Name: category_tag idx_16575532_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category_tag
    ADD CONSTRAINT idx_16575532_primary PRIMARY KEY (category_id, category_version_id, tag_id);


--
-- Name: category_translation idx_16575540_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category_translation
    ADD CONSTRAINT idx_16575540_primary PRIMARY KEY (category_id, category_version_id, language_id);


--
-- Name: cms_block idx_16575549_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cms_block
    ADD CONSTRAINT idx_16575549_primary PRIMARY KEY (id, version_id);


--
-- Name: cms_page idx_16575565_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cms_page
    ADD CONSTRAINT idx_16575565_primary PRIMARY KEY (id, version_id);


--
-- Name: cms_page_translation idx_16575577_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cms_page_translation
    ADD CONSTRAINT idx_16575577_primary PRIMARY KEY (cms_page_id, language_id, cms_page_version_id);


--
-- Name: cms_section idx_16575587_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cms_section
    ADD CONSTRAINT idx_16575587_primary PRIMARY KEY (id, version_id);


--
-- Name: cms_slot idx_16575608_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cms_slot
    ADD CONSTRAINT idx_16575608_primary PRIMARY KEY (id, version_id);


--
-- Name: cms_slot_translation idx_16575623_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cms_slot_translation
    ADD CONSTRAINT idx_16575623_primary PRIMARY KEY (cms_slot_id, cms_slot_version_id, language_id);


--
-- Name: consent_log idx_16575633_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consent_log
    ADD CONSTRAINT idx_16575633_primary PRIMARY KEY (id);


--
-- Name: consent_state idx_16575643_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consent_state
    ADD CONSTRAINT idx_16575643_primary PRIMARY KEY (id);


--
-- Name: country idx_16575654_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.country
    ADD CONSTRAINT idx_16575654_primary PRIMARY KEY (id);


--
-- Name: country_state idx_16575683_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.country_state
    ADD CONSTRAINT idx_16575683_primary PRIMARY KEY (id);


--
-- Name: country_state_translation idx_16575696_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.country_state_translation
    ADD CONSTRAINT idx_16575696_primary PRIMARY KEY (country_state_id, language_id);


--
-- Name: country_translation idx_16575704_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.country_translation
    ADD CONSTRAINT idx_16575704_primary PRIMARY KEY (country_id, language_id);


--
-- Name: currency idx_16575712_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency
    ADD CONSTRAINT idx_16575712_primary PRIMARY KEY (id);


--
-- Name: currency_country_rounding idx_16575725_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_country_rounding
    ADD CONSTRAINT idx_16575725_primary PRIMARY KEY (id);


--
-- Name: currency_translation idx_16575736_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_translation
    ADD CONSTRAINT idx_16575736_primary PRIMARY KEY (currency_id, language_id);


--
-- Name: custom_entity idx_16575744_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.custom_entity
    ADD CONSTRAINT idx_16575744_primary PRIMARY KEY (id);


--
-- Name: custom_field idx_16575755_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.custom_field
    ADD CONSTRAINT idx_16575755_primary PRIMARY KEY (id);


--
-- Name: custom_field_set idx_16575774_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.custom_field_set
    ADD CONSTRAINT idx_16575774_primary PRIMARY KEY (id);


--
-- Name: custom_field_set_relation idx_16575788_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.custom_field_set_relation
    ADD CONSTRAINT idx_16575788_primary PRIMARY KEY (id);


--
-- Name: customer idx_16575798_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer
    ADD CONSTRAINT idx_16575798_primary PRIMARY KEY (id);


--
-- Name: customer_address idx_16575829_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_address
    ADD CONSTRAINT idx_16575829_primary PRIMARY KEY (id);


--
-- Name: customer_group idx_16575842_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_group
    ADD CONSTRAINT idx_16575842_primary PRIMARY KEY (id);


--
-- Name: customer_group_registration_sales_channels idx_16575853_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_group_registration_sales_channels
    ADD CONSTRAINT idx_16575853_primary PRIMARY KEY (customer_group_id, sales_channel_id);


--
-- Name: customer_group_translation idx_16575861_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_group_translation
    ADD CONSTRAINT idx_16575861_primary PRIMARY KEY (customer_group_id, language_id);


--
-- Name: customer_recovery idx_16575869_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_recovery
    ADD CONSTRAINT idx_16575869_primary PRIMARY KEY (id);


--
-- Name: customer_tag idx_16575878_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_tag
    ADD CONSTRAINT idx_16575878_primary PRIMARY KEY (customer_id, tag_id);


--
-- Name: customer_wishlist idx_16575885_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_wishlist
    ADD CONSTRAINT idx_16575885_primary PRIMARY KEY (id);


--
-- Name: customer_wishlist_product idx_16575894_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_wishlist_product
    ADD CONSTRAINT idx_16575894_primary PRIMARY KEY (id);


--
-- Name: deleted_apps idx_16575904_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.deleted_apps
    ADD CONSTRAINT idx_16575904_primary PRIMARY KEY (name);


--
-- Name: delivery_time idx_16575911_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.delivery_time
    ADD CONSTRAINT idx_16575911_primary PRIMARY KEY (id);


--
-- Name: delivery_time_translation idx_16575921_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.delivery_time_translation
    ADD CONSTRAINT idx_16575921_primary PRIMARY KEY (delivery_time_id, language_id);


--
-- Name: document idx_16575929_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document
    ADD CONSTRAINT idx_16575929_primary PRIMARY KEY (id);


--
-- Name: document_base_config idx_16575942_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_base_config
    ADD CONSTRAINT idx_16575942_primary PRIMARY KEY (id);


--
-- Name: document_base_config_sales_channel idx_16575955_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_base_config_sales_channel
    ADD CONSTRAINT idx_16575955_primary PRIMARY KEY (id);


--
-- Name: document_file idx_16575964_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_file
    ADD CONSTRAINT idx_16575964_primary PRIMARY KEY (id);


--
-- Name: document_type idx_16575974_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_type
    ADD CONSTRAINT idx_16575974_primary PRIMARY KEY (id);


--
-- Name: document_type_translation idx_16575982_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_type_translation
    ADD CONSTRAINT idx_16575982_primary PRIMARY KEY (document_type_id, language_id);


--
-- Name: elasticsearch_index_task idx_16575990_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.elasticsearch_index_task
    ADD CONSTRAINT idx_16575990_primary PRIMARY KEY (id);


--
-- Name: flow idx_16576000_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flow
    ADD CONSTRAINT idx_16576000_primary PRIMARY KEY (id);


--
-- Name: flow_sequence idx_16576015_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flow_sequence
    ADD CONSTRAINT idx_16576015_primary PRIMARY KEY (id);


--
-- Name: flow_template idx_16576029_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flow_template
    ADD CONSTRAINT idx_16576029_primary PRIMARY KEY (id);


--
-- Name: import_export_file idx_16576037_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_export_file
    ADD CONSTRAINT idx_16576037_primary PRIMARY KEY (id);


--
-- Name: import_export_log idx_16576047_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_export_log
    ADD CONSTRAINT idx_16576047_primary PRIMARY KEY (id);


--
-- Name: import_export_profile idx_16576057_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_export_profile
    ADD CONSTRAINT idx_16576057_primary PRIMARY KEY (id);


--
-- Name: import_export_profile_translation idx_16576071_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_export_profile_translation
    ADD CONSTRAINT idx_16576071_primary PRIMARY KEY (import_export_profile_id, language_id);


--
-- Name: increment idx_16576079_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.increment
    ADD CONSTRAINT idx_16576079_primary PRIMARY KEY (pool, cluster, key);


--
-- Name: integration idx_16576090_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.integration
    ADD CONSTRAINT idx_16576090_primary PRIMARY KEY (id);


--
-- Name: integration_role idx_16576104_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.integration_role
    ADD CONSTRAINT idx_16576104_primary PRIMARY KEY (integration_id, acl_role_id);


--
-- Name: invalidation_tags idx_16576111_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invalidation_tags
    ADD CONSTRAINT idx_16576111_primary PRIMARY KEY (id);


--
-- Name: landing_page idx_16576118_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.landing_page
    ADD CONSTRAINT idx_16576118_primary PRIMARY KEY (id, version_id);


--
-- Name: landing_page_sales_channel idx_16576130_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.landing_page_sales_channel
    ADD CONSTRAINT idx_16576130_primary PRIMARY KEY (landing_page_id, landing_page_version_id, sales_channel_id);


--
-- Name: landing_page_tag idx_16576138_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.landing_page_tag
    ADD CONSTRAINT idx_16576138_primary PRIMARY KEY (landing_page_id, landing_page_version_id, tag_id);


--
-- Name: landing_page_translation idx_16576146_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.landing_page_translation
    ADD CONSTRAINT idx_16576146_primary PRIMARY KEY (landing_page_id, landing_page_version_id, language_id);


--
-- Name: language idx_16576155_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.language
    ADD CONSTRAINT idx_16576155_primary PRIMARY KEY (id);


--
-- Name: locale idx_16576168_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locale
    ADD CONSTRAINT idx_16576168_primary PRIMARY KEY (id);


--
-- Name: locale_translation idx_16576176_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locale_translation
    ADD CONSTRAINT idx_16576176_primary PRIMARY KEY (locale_id, language_id);


--
-- Name: log_entry idx_16576184_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_entry
    ADD CONSTRAINT idx_16576184_primary PRIMARY KEY (id);


--
-- Name: mail_header_footer idx_16576194_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mail_header_footer
    ADD CONSTRAINT idx_16576194_primary PRIMARY KEY (id);


--
-- Name: mail_header_footer_translation idx_16576203_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mail_header_footer_translation
    ADD CONSTRAINT idx_16576203_primary PRIMARY KEY (mail_header_footer_id, language_id);


--
-- Name: mail_template idx_16576211_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mail_template
    ADD CONSTRAINT idx_16576211_primary PRIMARY KEY (id);


--
-- Name: mail_template_media idx_16576222_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mail_template_media
    ADD CONSTRAINT idx_16576222_primary PRIMARY KEY (id);


--
-- Name: mail_template_translation idx_16576232_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mail_template_translation
    ADD CONSTRAINT idx_16576232_primary PRIMARY KEY (mail_template_id, language_id);


--
-- Name: mail_template_type idx_16576240_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mail_template_type
    ADD CONSTRAINT idx_16576240_primary PRIMARY KEY (id);


--
-- Name: mail_template_type_translation idx_16576248_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mail_template_type_translation
    ADD CONSTRAINT idx_16576248_primary PRIMARY KEY (mail_template_type_id, language_id);


--
-- Name: main_category idx_16576256_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.main_category
    ADD CONSTRAINT idx_16576256_primary PRIMARY KEY (id);


--
-- Name: mcp_tool_result_cache idx_16576268_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mcp_tool_result_cache
    ADD CONSTRAINT idx_16576268_primary PRIMARY KEY (id);


--
-- Name: mcp_toolset_session idx_16576279_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mcp_toolset_session
    ADD CONSTRAINT idx_16576279_primary PRIMARY KEY (session_id, toolset_name);


--
-- Name: measurement_display_unit idx_16576287_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.measurement_display_unit
    ADD CONSTRAINT idx_16576287_primary PRIMARY KEY (id);


--
-- Name: measurement_display_unit_translation idx_16576302_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.measurement_display_unit_translation
    ADD CONSTRAINT idx_16576302_primary PRIMARY KEY (measurement_display_unit_id, language_id);


--
-- Name: measurement_system idx_16576310_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.measurement_system
    ADD CONSTRAINT idx_16576310_primary PRIMARY KEY (id);


--
-- Name: measurement_system_translation idx_16576318_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.measurement_system_translation
    ADD CONSTRAINT idx_16576318_primary PRIMARY KEY (measurement_system_id, language_id);


--
-- Name: media idx_16576326_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media
    ADD CONSTRAINT idx_16576326_primary PRIMARY KEY (id);


--
-- Name: media_default_folder idx_16576335_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_default_folder
    ADD CONSTRAINT idx_16576335_primary PRIMARY KEY (id);


--
-- Name: media_folder idx_16576343_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_folder
    ADD CONSTRAINT idx_16576343_primary PRIMARY KEY (id);


--
-- Name: media_folder_configuration idx_16576353_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_folder_configuration
    ADD CONSTRAINT idx_16576353_primary PRIMARY KEY (id);


--
-- Name: media_folder_configuration_media_thumbnail_size idx_16576364_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_folder_configuration_media_thumbnail_size
    ADD CONSTRAINT idx_16576364_primary PRIMARY KEY (media_folder_configuration_id, media_thumbnail_size_id);


--
-- Name: media_tag idx_16576371_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_tag
    ADD CONSTRAINT idx_16576371_primary PRIMARY KEY (media_id, tag_id);


--
-- Name: media_thumbnail idx_16576378_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_thumbnail
    ADD CONSTRAINT idx_16576378_primary PRIMARY KEY (id);


--
-- Name: media_thumbnail_size idx_16576388_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_thumbnail_size
    ADD CONSTRAINT idx_16576388_primary PRIMARY KEY (id);


--
-- Name: media_translation idx_16576397_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_translation
    ADD CONSTRAINT idx_16576397_primary PRIMARY KEY (media_id, language_id);


--
-- Name: messenger_messages idx_16576406_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messenger_messages
    ADD CONSTRAINT idx_16576406_primary PRIMARY KEY (id);


--
-- Name: messenger_stats idx_16576419_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messenger_stats
    ADD CONSTRAINT idx_16576419_primary PRIMARY KEY (id);


--
-- Name: migration idx_16576427_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migration
    ADD CONSTRAINT idx_16576427_primary PRIMARY KEY (class);


--
-- Name: newsletter_recipient idx_16576434_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.newsletter_recipient
    ADD CONSTRAINT idx_16576434_primary PRIMARY KEY (id);


--
-- Name: newsletter_recipient_tag idx_16576446_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.newsletter_recipient_tag
    ADD CONSTRAINT idx_16576446_primary PRIMARY KEY (newsletter_recipient_id, tag_id);


--
-- Name: notification idx_16576453_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification
    ADD CONSTRAINT idx_16576453_primary PRIMARY KEY (id);


--
-- Name: number_range idx_16576464_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_range
    ADD CONSTRAINT idx_16576464_primary PRIMARY KEY (id);


--
-- Name: number_range_sales_channel idx_16576475_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_range_sales_channel
    ADD CONSTRAINT idx_16576475_primary PRIMARY KEY (id);


--
-- Name: number_range_state idx_16576484_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_range_state
    ADD CONSTRAINT idx_16576484_primary PRIMARY KEY (number_range_id);


--
-- Name: number_range_translation idx_16576493_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_range_translation
    ADD CONSTRAINT idx_16576493_primary PRIMARY KEY (number_range_id, language_id);


--
-- Name: number_range_type idx_16576501_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_range_type
    ADD CONSTRAINT idx_16576501_primary PRIMARY KEY (id);


--
-- Name: number_range_type_translation idx_16576509_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_range_type_translation
    ADD CONSTRAINT idx_16576509_primary PRIMARY KEY (number_range_type_id, language_id);


--
-- Name: oauth_auth_code idx_16576517_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_auth_code
    ADD CONSTRAINT idx_16576517_primary PRIMARY KEY (id);


--
-- Name: oauth_user idx_16576528_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_user
    ADD CONSTRAINT idx_16576528_primary PRIMARY KEY (id);


--
-- Name: order idx_16576539_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public."order"
    ADD CONSTRAINT idx_16576539_primary PRIMARY KEY (id, version_id);


--
-- Name: order_address idx_16576558_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_address
    ADD CONSTRAINT idx_16576558_primary PRIMARY KEY (id, version_id);


--
-- Name: order_customer idx_16576573_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_customer
    ADD CONSTRAINT idx_16576573_primary PRIMARY KEY (id, version_id);


--
-- Name: order_delivery idx_16576586_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_delivery
    ADD CONSTRAINT idx_16576586_primary PRIMARY KEY (id, version_id);


--
-- Name: order_delivery_position idx_16576602_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_delivery_position
    ADD CONSTRAINT idx_16576602_primary PRIMARY KEY (id, version_id);


--
-- Name: order_line_item idx_16576615_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_line_item
    ADD CONSTRAINT idx_16576615_primary PRIMARY KEY (id, version_id);


--
-- Name: order_line_item_download idx_16576637_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_line_item_download
    ADD CONSTRAINT idx_16576637_primary PRIMARY KEY (id, version_id);


--
-- Name: order_tag idx_16576652_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_tag
    ADD CONSTRAINT idx_16576652_primary PRIMARY KEY (order_id, order_version_id, tag_id);


--
-- Name: order_transaction idx_16576660_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_transaction
    ADD CONSTRAINT idx_16576660_primary PRIMARY KEY (id, version_id);


--
-- Name: order_transaction_capture idx_16576673_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_transaction_capture
    ADD CONSTRAINT idx_16576673_primary PRIMARY KEY (id, version_id);


--
-- Name: order_transaction_capture_refund idx_16576686_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_transaction_capture_refund
    ADD CONSTRAINT idx_16576686_primary PRIMARY KEY (id, version_id);


--
-- Name: order_transaction_capture_refund_position idx_16576699_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_transaction_capture_refund_position
    ADD CONSTRAINT idx_16576699_primary PRIMARY KEY (id, version_id);


--
-- Name: payment_method idx_16576714_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_method
    ADD CONSTRAINT idx_16576714_primary PRIMARY KEY (id);


--
-- Name: payment_method_translation idx_16576730_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_method_translation
    ADD CONSTRAINT idx_16576730_primary PRIMARY KEY (payment_method_id, language_id);


--
-- Name: payment_token idx_16576738_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_token
    ADD CONSTRAINT idx_16576738_primary PRIMARY KEY (token);


--
-- Name: plugin idx_16576744_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugin
    ADD CONSTRAINT idx_16576744_primary PRIMARY KEY (id);


--
-- Name: plugin_translation idx_16576759_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugin_translation
    ADD CONSTRAINT idx_16576759_primary PRIMARY KEY (plugin_id, language_id);


--
-- Name: product idx_16576768_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product
    ADD CONSTRAINT idx_16576768_primary PRIMARY KEY (id, version_id);


--
-- Name: product_category idx_16576786_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_category
    ADD CONSTRAINT idx_16576786_primary PRIMARY KEY (product_id, product_version_id, category_id, category_version_id);


--
-- Name: product_category_tree idx_16576795_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_category_tree
    ADD CONSTRAINT idx_16576795_primary PRIMARY KEY (product_id, product_version_id, category_id, category_version_id);


--
-- Name: product_configurator_setting idx_16576804_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_configurator_setting
    ADD CONSTRAINT idx_16576804_primary PRIMARY KEY (id, version_id);


--
-- Name: product_cross_selling idx_16576817_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_cross_selling
    ADD CONSTRAINT idx_16576817_primary PRIMARY KEY (id);


--
-- Name: product_cross_selling_assigned_products idx_16576833_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_cross_selling_assigned_products
    ADD CONSTRAINT idx_16576833_primary PRIMARY KEY (id);


--
-- Name: product_cross_selling_translation idx_16576845_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_cross_selling_translation
    ADD CONSTRAINT idx_16576845_primary PRIMARY KEY (product_cross_selling_id, language_id);


--
-- Name: product_custom_field_set idx_16576853_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_custom_field_set
    ADD CONSTRAINT idx_16576853_primary PRIMARY KEY (custom_field_set_id, product_id, product_version_id);


--
-- Name: product_download idx_16576861_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_download
    ADD CONSTRAINT idx_16576861_primary PRIMARY KEY (id, version_id);


--
-- Name: product_export idx_16576874_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_export
    ADD CONSTRAINT idx_16576874_primary PRIMARY KEY (id);


--
-- Name: product_feature_set idx_16576895_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_feature_set
    ADD CONSTRAINT idx_16576895_primary PRIMARY KEY (id);


--
-- Name: product_feature_set_translation idx_16576902_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_feature_set_translation
    ADD CONSTRAINT idx_16576902_primary PRIMARY KEY (product_feature_set_id, language_id);


--
-- Name: product_keyword_dictionary idx_16576910_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_keyword_dictionary
    ADD CONSTRAINT idx_16576910_primary PRIMARY KEY (id, language_id);


--
-- Name: product_manufacturer idx_16576918_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_manufacturer
    ADD CONSTRAINT idx_16576918_primary PRIMARY KEY (id, version_id);


--
-- Name: product_manufacturer_translation idx_16576926_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_manufacturer_translation
    ADD CONSTRAINT idx_16576926_primary PRIMARY KEY (product_manufacturer_id, product_manufacturer_version_id, language_id);


--
-- Name: product_media idx_16576935_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_media
    ADD CONSTRAINT idx_16576935_primary PRIMARY KEY (id, version_id);


--
-- Name: product_option idx_16576948_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_option
    ADD CONSTRAINT idx_16576948_primary PRIMARY KEY (product_id, product_version_id, property_group_option_id);


--
-- Name: product_price idx_16576956_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_price
    ADD CONSTRAINT idx_16576956_primary PRIMARY KEY (id, version_id);


--
-- Name: product_property idx_16576969_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_property
    ADD CONSTRAINT idx_16576969_primary PRIMARY KEY (product_id, product_version_id, property_group_option_id);


--
-- Name: product_review idx_16576977_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_review
    ADD CONSTRAINT idx_16576977_primary PRIMARY KEY (id);


--
-- Name: product_search_config idx_16576987_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_search_config
    ADD CONSTRAINT idx_16576987_primary PRIMARY KEY (id);


--
-- Name: product_search_config_field idx_16576999_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_search_config_field
    ADD CONSTRAINT idx_16576999_primary PRIMARY KEY (id);


--
-- Name: product_search_keyword idx_16577016_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_search_keyword
    ADD CONSTRAINT idx_16577016_primary PRIMARY KEY (id, version_id, language_id);


--
-- Name: product_sorting idx_16577029_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_sorting
    ADD CONSTRAINT idx_16577029_primary PRIMARY KEY (id);


--
-- Name: product_sorting_translation idx_16577043_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_sorting_translation
    ADD CONSTRAINT idx_16577043_primary PRIMARY KEY (product_sorting_id, language_id);


--
-- Name: product_stream idx_16577051_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_stream
    ADD CONSTRAINT idx_16577051_primary PRIMARY KEY (id);


--
-- Name: product_stream_filter idx_16577064_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_stream_filter
    ADD CONSTRAINT idx_16577064_primary PRIMARY KEY (id);


--
-- Name: product_stream_mapping idx_16577075_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_stream_mapping
    ADD CONSTRAINT idx_16577075_primary PRIMARY KEY (product_id, product_version_id, product_stream_id);


--
-- Name: product_stream_translation idx_16577083_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_stream_translation
    ADD CONSTRAINT idx_16577083_primary PRIMARY KEY (product_stream_id, language_id);


--
-- Name: product_tag idx_16577091_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_tag
    ADD CONSTRAINT idx_16577091_primary PRIMARY KEY (product_id, product_version_id, tag_id);


--
-- Name: product_translation idx_16577099_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_translation
    ADD CONSTRAINT idx_16577099_primary PRIMARY KEY (product_id, product_version_id, language_id);


--
-- Name: product_visibility idx_16577108_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_visibility
    ADD CONSTRAINT idx_16577108_primary PRIMARY KEY (id);


--
-- Name: promotion idx_16577119_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion
    ADD CONSTRAINT idx_16577119_primary PRIMARY KEY (id);


--
-- Name: promotion_cart_rule idx_16577144_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_cart_rule
    ADD CONSTRAINT idx_16577144_primary PRIMARY KEY (promotion_id, rule_id);


--
-- Name: promotion_discount idx_16577151_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_discount
    ADD CONSTRAINT idx_16577151_primary PRIMARY KEY (id);


--
-- Name: promotion_discount_prices idx_16577164_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_discount_prices
    ADD CONSTRAINT idx_16577164_primary PRIMARY KEY (id);


--
-- Name: promotion_discount_rule idx_16577175_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_discount_rule
    ADD CONSTRAINT idx_16577175_primary PRIMARY KEY (discount_id, rule_id);


--
-- Name: promotion_individual_code idx_16577182_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_individual_code
    ADD CONSTRAINT idx_16577182_primary PRIMARY KEY (id);


--
-- Name: promotion_order_rule idx_16577190_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_order_rule
    ADD CONSTRAINT idx_16577190_primary PRIMARY KEY (promotion_id, rule_id);


--
-- Name: promotion_persona_customer idx_16577197_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_persona_customer
    ADD CONSTRAINT idx_16577197_primary PRIMARY KEY (promotion_id, customer_id);


--
-- Name: promotion_persona_rule idx_16577204_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_persona_rule
    ADD CONSTRAINT idx_16577204_primary PRIMARY KEY (promotion_id, rule_id);


--
-- Name: promotion_sales_channel idx_16577211_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_sales_channel
    ADD CONSTRAINT idx_16577211_primary PRIMARY KEY (id);


--
-- Name: promotion_setgroup idx_16577222_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_setgroup
    ADD CONSTRAINT idx_16577222_primary PRIMARY KEY (id);


--
-- Name: promotion_setgroup_rule idx_16577233_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_setgroup_rule
    ADD CONSTRAINT idx_16577233_primary PRIMARY KEY (setgroup_id, rule_id);


--
-- Name: promotion_translation idx_16577240_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_translation
    ADD CONSTRAINT idx_16577240_primary PRIMARY KEY (promotion_id, language_id);


--
-- Name: property_group idx_16577248_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.property_group
    ADD CONSTRAINT idx_16577248_primary PRIMARY KEY (id);


--
-- Name: property_group_option idx_16577262_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.property_group_option
    ADD CONSTRAINT idx_16577262_primary PRIMARY KEY (id);


--
-- Name: property_group_option_translation idx_16577270_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.property_group_option_translation
    ADD CONSTRAINT idx_16577270_primary PRIMARY KEY (property_group_option_id, language_id);


--
-- Name: property_group_translation idx_16577280_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.property_group_translation
    ADD CONSTRAINT idx_16577280_primary PRIMARY KEY (property_group_id, language_id);


--
-- Name: refresh_token idx_16577289_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.refresh_token
    ADD CONSTRAINT idx_16577289_primary PRIMARY KEY (id);


--
-- Name: rule idx_16577299_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rule
    ADD CONSTRAINT idx_16577299_primary PRIMARY KEY (id);


--
-- Name: rule_condition idx_16577310_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rule_condition
    ADD CONSTRAINT idx_16577310_primary PRIMARY KEY (id);


--
-- Name: rule_tag idx_16577321_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rule_tag
    ADD CONSTRAINT idx_16577321_primary PRIMARY KEY (rule_id, tag_id);


--
-- Name: sales_channel idx_16577328_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel
    ADD CONSTRAINT idx_16577328_primary PRIMARY KEY (id);


--
-- Name: sales_channel_analytics idx_16577354_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_analytics
    ADD CONSTRAINT idx_16577354_primary PRIMARY KEY (id);


--
-- Name: sales_channel_api_context idx_16577372_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_api_context
    ADD CONSTRAINT idx_16577372_primary PRIMARY KEY (token);


--
-- Name: sales_channel_country idx_16577379_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_country
    ADD CONSTRAINT idx_16577379_primary PRIMARY KEY (sales_channel_id, country_id);


--
-- Name: sales_channel_currency idx_16577386_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_currency
    ADD CONSTRAINT idx_16577386_primary PRIMARY KEY (sales_channel_id, currency_id);


--
-- Name: sales_channel_domain idx_16577393_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_domain
    ADD CONSTRAINT idx_16577393_primary PRIMARY KEY (id);


--
-- Name: sales_channel_file idx_16577408_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_file
    ADD CONSTRAINT idx_16577408_primary PRIMARY KEY (id);


--
-- Name: sales_channel_language idx_16577420_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_language
    ADD CONSTRAINT idx_16577420_primary PRIMARY KEY (sales_channel_id, language_id);


--
-- Name: sales_channel_payment_method idx_16577427_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_payment_method
    ADD CONSTRAINT idx_16577427_primary PRIMARY KEY (sales_channel_id, payment_method_id);


--
-- Name: sales_channel_shipping_method idx_16577434_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_shipping_method
    ADD CONSTRAINT idx_16577434_primary PRIMARY KEY (sales_channel_id, shipping_method_id);


--
-- Name: sales_channel_tracking_customer idx_16577441_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_tracking_customer
    ADD CONSTRAINT idx_16577441_primary PRIMARY KEY (id);


--
-- Name: sales_channel_tracking_order idx_16577450_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_tracking_order
    ADD CONSTRAINT idx_16577450_primary PRIMARY KEY (id);


--
-- Name: sales_channel_translation idx_16577460_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_translation
    ADD CONSTRAINT idx_16577460_primary PRIMARY KEY (sales_channel_id, language_id);


--
-- Name: sales_channel_type idx_16577470_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_type
    ADD CONSTRAINT idx_16577470_primary PRIMARY KEY (id);


--
-- Name: sales_channel_type_translation idx_16577477_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_type_translation
    ADD CONSTRAINT idx_16577477_primary PRIMARY KEY (sales_channel_type_id, language_id);


--
-- Name: salutation idx_16577485_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salutation
    ADD CONSTRAINT idx_16577485_primary PRIMARY KEY (id);


--
-- Name: salutation_translation idx_16577495_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salutation_translation
    ADD CONSTRAINT idx_16577495_primary PRIMARY KEY (salutation_id, language_id);


--
-- Name: scheduled_task idx_16577503_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scheduled_task
    ADD CONSTRAINT idx_16577503_primary PRIMARY KEY (id);


--
-- Name: script idx_16577516_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.script
    ADD CONSTRAINT idx_16577516_primary PRIMARY KEY (id);


--
-- Name: seo_url idx_16577527_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.seo_url
    ADD CONSTRAINT idx_16577527_primary PRIMARY KEY (id);


--
-- Name: seo_url_template idx_16577543_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.seo_url_template
    ADD CONSTRAINT idx_16577543_primary PRIMARY KEY (id);


--
-- Name: shipping_method idx_16577556_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_method
    ADD CONSTRAINT idx_16577556_primary PRIMARY KEY (id);


--
-- Name: shipping_method_price idx_16577570_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_method_price
    ADD CONSTRAINT idx_16577570_primary PRIMARY KEY (id);


--
-- Name: shipping_method_tag idx_16577578_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_method_tag
    ADD CONSTRAINT idx_16577578_primary PRIMARY KEY (shipping_method_id, tag_id);


--
-- Name: shipping_method_translation idx_16577585_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_method_translation
    ADD CONSTRAINT idx_16577585_primary PRIMARY KEY (shipping_method_id, language_id);


--
-- Name: snippet idx_16577593_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.snippet
    ADD CONSTRAINT idx_16577593_primary PRIMARY KEY (id);


--
-- Name: snippet_set idx_16577604_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.snippet_set
    ADD CONSTRAINT idx_16577604_primary PRIMARY KEY (id);


--
-- Name: state_machine idx_16577614_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine
    ADD CONSTRAINT idx_16577614_primary PRIMARY KEY (id);


--
-- Name: state_machine_history idx_16577622_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_history
    ADD CONSTRAINT idx_16577622_primary PRIMARY KEY (id);


--
-- Name: state_machine_state idx_16577636_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_state
    ADD CONSTRAINT idx_16577636_primary PRIMARY KEY (id);


--
-- Name: state_machine_state_translation idx_16577645_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_state_translation
    ADD CONSTRAINT idx_16577645_primary PRIMARY KEY (language_id, state_machine_state_id);


--
-- Name: state_machine_transition idx_16577653_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_transition
    ADD CONSTRAINT idx_16577653_primary PRIMARY KEY (id);


--
-- Name: state_machine_translation idx_16577664_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_translation
    ADD CONSTRAINT idx_16577664_primary PRIMARY KEY (language_id, state_machine_id);


--
-- Name: system_config idx_16577672_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.system_config
    ADD CONSTRAINT idx_16577672_primary PRIMARY KEY (id);


--
-- Name: tag idx_16577681_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tag
    ADD CONSTRAINT idx_16577681_primary PRIMARY KEY (id);


--
-- Name: tax idx_16577689_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax
    ADD CONSTRAINT idx_16577689_primary PRIMARY KEY (id);


--
-- Name: tax_provider idx_16577699_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_provider
    ADD CONSTRAINT idx_16577699_primary PRIMARY KEY (id);


--
-- Name: tax_provider_translation idx_16577711_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_provider_translation
    ADD CONSTRAINT idx_16577711_primary PRIMARY KEY (tax_provider_id, language_id);


--
-- Name: tax_rule idx_16577719_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_rule
    ADD CONSTRAINT idx_16577719_primary PRIMARY KEY (id);


--
-- Name: tax_rule_type idx_16577729_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_rule_type
    ADD CONSTRAINT idx_16577729_primary PRIMARY KEY (id);


--
-- Name: tax_rule_type_translation idx_16577738_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_rule_type_translation
    ADD CONSTRAINT idx_16577738_primary PRIMARY KEY (tax_rule_type_id, language_id);


--
-- Name: theme idx_16577746_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme
    ADD CONSTRAINT idx_16577746_primary PRIMARY KEY (id);


--
-- Name: theme_child idx_16577756_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme_child
    ADD CONSTRAINT idx_16577756_primary PRIMARY KEY (parent_id, child_id);


--
-- Name: theme_media idx_16577763_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme_media
    ADD CONSTRAINT idx_16577763_primary PRIMARY KEY (theme_id, media_id);


--
-- Name: theme_runtime_config idx_16577770_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme_runtime_config
    ADD CONSTRAINT idx_16577770_primary PRIMARY KEY (theme_id);


--
-- Name: theme_sales_channel idx_16577780_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme_sales_channel
    ADD CONSTRAINT idx_16577780_primary PRIMARY KEY (sales_channel_id);


--
-- Name: theme_translation idx_16577787_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme_translation
    ADD CONSTRAINT idx_16577787_primary PRIMARY KEY (theme_id, language_id);


--
-- Name: unit idx_16577795_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unit
    ADD CONSTRAINT idx_16577795_primary PRIMARY KEY (id);


--
-- Name: unit_translation idx_16577802_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unit_translation
    ADD CONSTRAINT idx_16577802_primary PRIMARY KEY (unit_id, language_id);


--
-- Name: usage_data_entity_deletion idx_16577810_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usage_data_entity_deletion
    ADD CONSTRAINT idx_16577810_primary PRIMARY KEY (id);


--
-- Name: user idx_16577819_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public."user"
    ADD CONSTRAINT idx_16577819_primary PRIMARY KEY (id);


--
-- Name: user_access_key idx_16577836_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_access_key
    ADD CONSTRAINT idx_16577836_primary PRIMARY KEY (id);


--
-- Name: user_config idx_16577846_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_config
    ADD CONSTRAINT idx_16577846_primary PRIMARY KEY (id);


--
-- Name: user_recovery idx_16577855_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_recovery
    ADD CONSTRAINT idx_16577855_primary PRIMARY KEY (id);


--
-- Name: version idx_16577864_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version
    ADD CONSTRAINT idx_16577864_primary PRIMARY KEY (id);


--
-- Name: version_commit idx_16577873_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_commit
    ADD CONSTRAINT idx_16577873_primary PRIMARY KEY (id);


--
-- Name: version_commit_data idx_16577886_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_commit_data
    ADD CONSTRAINT idx_16577886_primary PRIMARY KEY (id);


--
-- Name: webhook idx_16577900_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook
    ADD CONSTRAINT idx_16577900_primary PRIMARY KEY (id);


--
-- Name: webhook_delivery idx_16577916_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_delivery
    ADD CONSTRAINT idx_16577916_primary PRIMARY KEY (id);


--
-- Name: webhook_event_log idx_16577930_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_event_log
    ADD CONSTRAINT idx_16577930_primary PRIMARY KEY (id);


--
-- Name: webhook_stream idx_16577943_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_stream
    ADD CONSTRAINT idx_16577943_primary PRIMARY KEY (id);


--
-- Name: idx_16575238_fk.acl_user_role.acl_role_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575238_fk.acl_user_role.acl_role_id" ON public.acl_user_role USING btree (acl_role_id);


--
-- Name: idx_16575256_fk.app.acl_role_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575256_fk.app.acl_role_id" ON public.app USING btree (acl_role_id);


--
-- Name: idx_16575256_fk.app.integration_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575256_fk.app.integration_id" ON public.app USING btree (integration_id);


--
-- Name: idx_16575256_uniq.name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575256_uniq.name" ON public.app USING btree (name);


--
-- Name: idx_16575282_fk.app_action_button.app_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575282_fk.app_action_button.app_id" ON public.app_action_button USING btree (app_id);


--
-- Name: idx_16575282_uniq.app_action_button.action; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575282_uniq.app_action_button.action" ON public.app_action_button USING btree (action, app_id);


--
-- Name: idx_16575294_fk.app_action_button_translation.app_action_button; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575294_fk.app_action_button_translation.app_action_button" ON public.app_action_button_translation USING btree (app_action_button_id);


--
-- Name: idx_16575294_fk.app_action_button_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575294_fk.app_action_button_translation.language_id" ON public.app_action_button_translation USING btree (language_id);


--
-- Name: idx_16575302_fk.app_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575302_fk.app_id" ON public.app_administration_snippet USING btree (app_id);


--
-- Name: idx_16575302_fk.locale_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575302_fk.locale_id" ON public.app_administration_snippet USING btree (locale_id);


--
-- Name: idx_16575312_fk.app_cms_block.app_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575312_fk.app_cms_block.app_id" ON public.app_cms_block USING btree (app_id);


--
-- Name: idx_16575324_fk.app_cms_block_translation.app_cms_block_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575324_fk.app_cms_block_translation.app_cms_block_id" ON public.app_cms_block_translation USING btree (app_cms_block_id);


--
-- Name: idx_16575324_fk.app_cms_block_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575324_fk.app_cms_block_translation.language_id" ON public.app_cms_block_translation USING btree (language_id);


--
-- Name: idx_16575339_fk.app_feature.app_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575339_fk.app_feature.app_id" ON public.app_feature USING btree (app_id);


--
-- Name: idx_16575339_idx.app_feature.type; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575339_idx.app_feature.type" ON public.app_feature USING btree (type);


--
-- Name: idx_16575339_uniq.app_feature.app_name_type_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575339_uniq.app_feature.app_name_type_name" ON public.app_feature USING btree (app_name, type, name);


--
-- Name: idx_16575350_fk.app_flow_action.app_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575350_fk.app_flow_action.app_id" ON public.app_flow_action USING btree (app_id);


--
-- Name: idx_16575350_uniq.app_flow_action.name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575350_uniq.app_flow_action.name" ON public.app_flow_action USING btree (name);


--
-- Name: idx_16575362_fk.app_flow_action_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575362_fk.app_flow_action_translation.language_id" ON public.app_flow_action_translation USING btree (language_id);


--
-- Name: idx_16575370_fk.app_flow_event.app_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575370_fk.app_flow_event.app_id" ON public.app_flow_event USING btree (app_id);


--
-- Name: idx_16575370_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_16575370_name ON public.app_flow_event USING btree (name);


--
-- Name: idx_16575370_uniq.app_flow_event.name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575370_uniq.app_flow_event.name" ON public.app_flow_event USING btree (name);


--
-- Name: idx_16575380_uniq.app_mcp_prompt.app_id_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575380_uniq.app_mcp_prompt.app_id_name" ON public.app_mcp_prompt USING btree (app_id, name);


--
-- Name: idx_16575390_fk.app_mcp_prompt_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575390_fk.app_mcp_prompt_translation.language_id" ON public.app_mcp_prompt_translation USING btree (language_id);


--
-- Name: idx_16575398_uniq.app_mcp_resource.app_id_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575398_uniq.app_mcp_resource.app_id_name" ON public.app_mcp_resource USING btree (app_id, name);


--
-- Name: idx_16575409_fk.app_mcp_resource_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575409_fk.app_mcp_resource_translation.language_id" ON public.app_mcp_resource_translation USING btree (language_id);


--
-- Name: idx_16575417_uniq.app_mcp_tool.app_id_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575417_uniq.app_mcp_tool.app_id_name" ON public.app_mcp_tool USING btree (app_id, name);


--
-- Name: idx_16575427_fk.app_mcp_tool_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575427_fk.app_mcp_tool_translation.language_id" ON public.app_mcp_tool_translation USING btree (language_id);


--
-- Name: idx_16575435_fk.app_payment_method.app_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575435_fk.app_payment_method.app_id" ON public.app_payment_method USING btree (app_id);


--
-- Name: idx_16575435_fk.app_payment_method.original_media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575435_fk.app_payment_method.original_media_id" ON public.app_payment_method USING btree (original_media_id);


--
-- Name: idx_16575435_uniq.app_payment_method.payment_method_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575435_uniq.app_payment_method.payment_method_id" ON public.app_payment_method USING btree (payment_method_id);


--
-- Name: idx_16575445_fk.app_script_condition.app_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575445_fk.app_script_condition.app_id" ON public.app_script_condition USING btree (app_id);


--
-- Name: idx_16575456_fk.app_script_condition_translation.app_script_con; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575456_fk.app_script_condition_translation.app_script_con" ON public.app_script_condition_translation USING btree (app_script_condition_id);


--
-- Name: idx_16575456_fk.app_script_condition_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575456_fk.app_script_condition_translation.language_id" ON public.app_script_condition_translation USING btree (language_id);


--
-- Name: idx_16575464_fk.app_shipping_method.app_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575464_fk.app_shipping_method.app_id" ON public.app_shipping_method USING btree (app_id);


--
-- Name: idx_16575464_fk.app_shipping_method.original_media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575464_fk.app_shipping_method.original_media_id" ON public.app_shipping_method USING btree (original_media_id);


--
-- Name: idx_16575464_uniq.app_shipping_method.shipping_method_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575464_uniq.app_shipping_method.shipping_method_id" ON public.app_shipping_method USING btree (shipping_method_id);


--
-- Name: idx_16575474_fk.template.app_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575474_fk.template.app_id" ON public.app_template USING btree (app_id);


--
-- Name: idx_16575474_idx.template.path; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575474_idx.template.path" ON public.app_template USING btree (path);


--
-- Name: idx_16575485_fk.app_translation.app_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575485_fk.app_translation.app_id" ON public.app_translation USING btree (app_id);


--
-- Name: idx_16575485_fk.app_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575485_fk.app_translation.language_id" ON public.app_translation USING btree (language_id);


--
-- Name: idx_16575494_auto_increment; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_16575494_auto_increment ON public.cart USING btree (auto_increment);


--
-- Name: idx_16575494_idx.cart.created_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575494_idx.cart.created_at" ON public.cart USING btree (created_at);


--
-- Name: idx_16575507_auto_increment; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_16575507_auto_increment ON public.category USING btree (auto_increment);


--
-- Name: idx_16575507_fk.category.after_category_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575507_fk.category.after_category_id" ON public.category USING btree (after_category_id, after_category_version_id);


--
-- Name: idx_16575507_fk.category.cms_page_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575507_fk.category.cms_page_id" ON public.category USING btree (cms_page_id, cms_page_version_id);


--
-- Name: idx_16575507_fk.category.custom_entity_type_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575507_fk.category.custom_entity_type_id" ON public.category USING btree (custom_entity_type_id);


--
-- Name: idx_16575507_fk.category.media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575507_fk.category.media_id" ON public.category USING btree (media_id);


--
-- Name: idx_16575507_fk.category.parent_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575507_fk.category.parent_id" ON public.category USING btree (parent_id, parent_version_id);


--
-- Name: idx_16575507_fk.category.product_stream_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575507_fk.category.product_stream_id" ON public.category USING btree (product_stream_id);


--
-- Name: idx_16575507_idx.level; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575507_idx.level" ON public.category USING btree (level);


--
-- Name: idx_16575532_fk.category_tag.tag_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575532_fk.category_tag.tag_id" ON public.category_tag USING btree (tag_id);


--
-- Name: idx_16575540_fk.category_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575540_fk.category_translation.language_id" ON public.category_translation USING btree (language_id);


--
-- Name: idx_16575549_fk.cms_block.background_media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575549_fk.cms_block.background_media_id" ON public.cms_block USING btree (background_media_id);


--
-- Name: idx_16575549_fk.cms_block.cms_section_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575549_fk.cms_block.cms_section_id" ON public.cms_block USING btree (cms_section_id, cms_section_version_id);


--
-- Name: idx_16575565_fk.cms_page.preview_media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575565_fk.cms_page.preview_media_id" ON public.cms_page USING btree (preview_media_id);


--
-- Name: idx_16575577_fk.cms_page_translation.cms_page_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575577_fk.cms_page_translation.cms_page_id" ON public.cms_page_translation USING btree (cms_page_id, cms_page_version_id);


--
-- Name: idx_16575577_fk.cms_page_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575577_fk.cms_page_translation.language_id" ON public.cms_page_translation USING btree (language_id);


--
-- Name: idx_16575587_fk.cms_section.background_media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575587_fk.cms_section.background_media_id" ON public.cms_section USING btree (background_media_id);


--
-- Name: idx_16575587_fk.cms_section.cms_page_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575587_fk.cms_section.cms_page_id" ON public.cms_section USING btree (cms_page_id, cms_page_version_id);


--
-- Name: idx_16575608_fk.cms_slot.cms_block_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575608_fk.cms_slot.cms_block_id" ON public.cms_slot USING btree (cms_block_id, cms_block_version_id);


--
-- Name: idx_16575623_fk.cms_slot_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575623_fk.cms_slot_translation.language_id" ON public.cms_slot_translation USING btree (language_id);


--
-- Name: idx_16575633_idx.consent_log.history; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575633_idx.consent_log.history" ON public.consent_log USING btree (consent_name);


--
-- Name: idx_16575643_uniq.consent_state; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575643_uniq.consent_state" ON public.consent_state USING btree (name, identifier);


--
-- Name: idx_16575683_fk.country_state.country_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575683_fk.country_state.country_id" ON public.country_state USING btree (country_id);


--
-- Name: idx_16575696_fk.country_state_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575696_fk.country_state_translation.language_id" ON public.country_state_translation USING btree (language_id);


--
-- Name: idx_16575704_fk.country_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575704_fk.country_translation.language_id" ON public.country_translation USING btree (language_id);


--
-- Name: idx_16575712_uniq.currency.iso_code; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575712_uniq.currency.iso_code" ON public.currency USING btree (iso_code);


--
-- Name: idx_16575725_country_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_16575725_country_id ON public.currency_country_rounding USING btree (country_id);


--
-- Name: idx_16575725_currency_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_16575725_currency_id ON public.currency_country_rounding USING btree (currency_id);


--
-- Name: idx_16575736_fk.currency_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575736_fk.currency_translation.language_id" ON public.currency_translation USING btree (language_id);


--
-- Name: idx_16575744_app_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_16575744_app_id ON public.custom_entity USING btree (app_id);


--
-- Name: idx_16575744_fk.custom_entity.plugin_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575744_fk.custom_entity.plugin_id" ON public.custom_entity USING btree (plugin_id);


--
-- Name: idx_16575744_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_16575744_name ON public.custom_entity USING btree (name);


--
-- Name: idx_16575755_fk.custom_field.set_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575755_fk.custom_field.set_id" ON public.custom_field USING btree (set_id);


--
-- Name: idx_16575755_uniq.custom_field.name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575755_uniq.custom_field.name" ON public.custom_field USING btree (name);


--
-- Name: idx_16575774_fk.custom_field_set.app_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575774_fk.custom_field_set.app_id" ON public.custom_field_set USING btree (app_id);


--
-- Name: idx_16575788_uniq.custom_field_set_relation.entity_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575788_uniq.custom_field_set_relation.entity_name" ON public.custom_field_set_relation USING btree (set_id, entity_name);


--
-- Name: idx_16575798_fk.customer.bound_sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575798_fk.customer.bound_sales_channel_id" ON public.customer USING btree (bound_sales_channel_id);


--
-- Name: idx_16575798_fk.customer.created_by_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575798_fk.customer.created_by_id" ON public.customer USING btree (created_by_id);


--
-- Name: idx_16575798_fk.customer.customer_group_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575798_fk.customer.customer_group_id" ON public.customer USING btree (customer_group_id);


--
-- Name: idx_16575798_fk.customer.default_payment_method_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575798_fk.customer.default_payment_method_id" ON public.customer USING btree (default_payment_method_id);


--
-- Name: idx_16575798_fk.customer.last_payment_method_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575798_fk.customer.last_payment_method_id" ON public.customer USING btree (last_payment_method_id);


--
-- Name: idx_16575798_fk.customer.requested_customer_group_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575798_fk.customer.requested_customer_group_id" ON public.customer USING btree (requested_customer_group_id);


--
-- Name: idx_16575798_fk.customer.sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575798_fk.customer.sales_channel_id" ON public.customer USING btree (sales_channel_id);


--
-- Name: idx_16575798_fk.customer.salutation_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575798_fk.customer.salutation_id" ON public.customer USING btree (salutation_id);


--
-- Name: idx_16575798_fk.customer.updated_by_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575798_fk.customer.updated_by_id" ON public.customer USING btree (updated_by_id);


--
-- Name: idx_16575798_hash; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_16575798_hash ON public.customer USING btree (hash);


--
-- Name: idx_16575798_idx.customer.default_billing_address_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575798_idx.customer.default_billing_address_id" ON public.customer USING btree (default_billing_address_id);


--
-- Name: idx_16575798_idx.customer.default_shipping_address_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575798_idx.customer.default_shipping_address_id" ON public.customer USING btree (default_shipping_address_id);


--
-- Name: idx_16575798_idx.email; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575798_idx.email" ON public.customer USING btree (email);


--
-- Name: idx_16575798_idx.firstlogin; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575798_idx.firstlogin" ON public.customer USING btree (first_login);


--
-- Name: idx_16575798_idx.lastlogin; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575798_idx.lastlogin" ON public.customer USING btree (last_login);


--
-- Name: idx_16575798_uniq.auto_increment; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575798_uniq.auto_increment" ON public.customer USING btree (auto_increment);


--
-- Name: idx_16575829_fk.customer_address.country_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575829_fk.customer_address.country_id" ON public.customer_address USING btree (country_id);


--
-- Name: idx_16575829_fk.customer_address.country_state_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575829_fk.customer_address.country_state_id" ON public.customer_address USING btree (country_state_id);


--
-- Name: idx_16575829_fk.customer_address.customer_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575829_fk.customer_address.customer_id" ON public.customer_address USING btree (customer_id);


--
-- Name: idx_16575829_fk.customer_address.salutation_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575829_fk.customer_address.salutation_id" ON public.customer_address USING btree (salutation_id);


--
-- Name: idx_16575853_fk.customer_group_registration_sales_channels.cust; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575853_fk.customer_group_registration_sales_channels.cust" ON public.customer_group_registration_sales_channels USING btree (customer_group_id);


--
-- Name: idx_16575853_fk.customer_group_registration_sales_channels.sale; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575853_fk.customer_group_registration_sales_channels.sale" ON public.customer_group_registration_sales_channels USING btree (sales_channel_id);


--
-- Name: idx_16575861_fk.customer_group_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575861_fk.customer_group_translation.language_id" ON public.customer_group_translation USING btree (language_id);


--
-- Name: idx_16575869_uniq.customer_recovery.customer_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575869_uniq.customer_recovery.customer_id" ON public.customer_recovery USING btree (customer_id);


--
-- Name: idx_16575878_fk.customer_tag.tag_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575878_fk.customer_tag.tag_id" ON public.customer_tag USING btree (tag_id);


--
-- Name: idx_16575885_fk.customer_wishlist.customer_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575885_fk.customer_wishlist.customer_id" ON public.customer_wishlist USING btree (customer_id);


--
-- Name: idx_16575885_fk.customer_wishlist.sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575885_fk.customer_wishlist.sales_channel_id" ON public.customer_wishlist USING btree (sales_channel_id);


--
-- Name: idx_16575885_uniq.customer_wishlist; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575885_uniq.customer_wishlist" ON public.customer_wishlist USING btree (sales_channel_id, customer_id);


--
-- Name: idx_16575894_fk.customer_wishlist_product.customer_wishlist_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575894_fk.customer_wishlist_product.customer_wishlist_id" ON public.customer_wishlist_product USING btree (customer_wishlist_id);


--
-- Name: idx_16575894_fk.customer_wishlist_product.product_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575894_fk.customer_wishlist_product.product_id" ON public.customer_wishlist_product USING btree (product_id, product_version_id);


--
-- Name: idx_16575894_uniq.customer_wishlist.sales_channel_id__customer_; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575894_uniq.customer_wishlist.sales_channel_id__customer_" ON public.customer_wishlist_product USING btree (customer_wishlist_id, product_id);


--
-- Name: idx_16575921_fk.delivery_time_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575921_fk.delivery_time_translation.language_id" ON public.delivery_time_translation USING btree (language_id);


--
-- Name: idx_16575929_fk.document.document_a11y_media_file_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575929_fk.document.document_a11y_media_file_id" ON public.document USING btree (document_a11y_media_file_id);


--
-- Name: idx_16575929_fk.document.document_media_file_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575929_fk.document.document_media_file_id" ON public.document USING btree (document_media_file_id);


--
-- Name: idx_16575929_fk.document.document_type_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575929_fk.document.document_type_id" ON public.document USING btree (document_type_id);


--
-- Name: idx_16575929_fk.document.order_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575929_fk.document.order_id" ON public.document USING btree (order_id, order_version_id);


--
-- Name: idx_16575929_fk.document.referenced_document_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575929_fk.document.referenced_document_id" ON public.document USING btree (referenced_document_id);


--
-- Name: idx_16575929_idx.document.document_number; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575929_idx.document.document_number" ON public.document USING btree (document_number);


--
-- Name: idx_16575929_uniq.deep_link_code; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575929_uniq.deep_link_code" ON public.document USING btree (deep_link_code);


--
-- Name: idx_16575929_uniq.document.document_number__type_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575929_uniq.document.document_number__type_name" ON public.document USING btree (document_number, type_name);


--
-- Name: idx_16575942_fk.document_base_config.logo_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575942_fk.document_base_config.logo_id" ON public.document_base_config USING btree (logo_id);


--
-- Name: idx_16575942_idx.document_base_config.type_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575942_idx.document_base_config.type_id" ON public.document_base_config USING btree (document_type_id);


--
-- Name: idx_16575955_fk.document_base_config_sales_channel.document_bas; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575955_fk.document_base_config_sales_channel.document_bas" ON public.document_base_config_sales_channel USING btree (document_base_config_id);


--
-- Name: idx_16575955_fk.document_base_config_sales_channel.sales_channe; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575955_fk.document_base_config_sales_channel.sales_channe" ON public.document_base_config_sales_channel USING btree (sales_channel_id);


--
-- Name: idx_16575955_uniq.document_base_configuration_id__sales_channel; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575955_uniq.document_base_configuration_id__sales_channel" ON public.document_base_config_sales_channel USING btree (document_type_id, sales_channel_id);


--
-- Name: idx_16575964_idx.document_file.document_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575964_idx.document_file.document_id" ON public.document_file USING btree (document_id);


--
-- Name: idx_16575964_uniq.document_file.document_id__document_format; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575964_uniq.document_file.document_id__document_format" ON public.document_file USING btree (document_id, document_format);


--
-- Name: idx_16575964_uniq.document_file.media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575964_uniq.document_file.media_id" ON public.document_file USING btree (media_id);


--
-- Name: idx_16575974_uniq.document_type.technical_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16575974_uniq.document_type.technical_name" ON public.document_type USING btree (technical_name);


--
-- Name: idx_16575982_fk.document_type_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16575982_fk.document_type_translation.language_id" ON public.document_type_translation USING btree (language_id);


--
-- Name: idx_16576000_fk.flow.app_flow_event_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576000_fk.flow.app_flow_event_id" ON public.flow USING btree (app_flow_event_id);


--
-- Name: idx_16576000_idx.flow.event_name; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576000_idx.flow.event_name" ON public.flow USING btree (event_name, priority);


--
-- Name: idx_16576015_fk.flow_sequence.app_flow_action_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576015_fk.flow_sequence.app_flow_action_id" ON public.flow_sequence USING btree (app_flow_action_id);


--
-- Name: idx_16576015_fk.flow_sequence.flow_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576015_fk.flow_sequence.flow_id" ON public.flow_sequence USING btree (flow_id);


--
-- Name: idx_16576015_fk.flow_sequence.parent_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576015_fk.flow_sequence.parent_id" ON public.flow_sequence USING btree (parent_id);


--
-- Name: idx_16576015_fk.flow_sequence.rule_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576015_fk.flow_sequence.rule_id" ON public.flow_sequence USING btree (rule_id);


--
-- Name: idx_16576047_fk.import_export_log.file_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576047_fk.import_export_log.file_id" ON public.import_export_log USING btree (file_id);


--
-- Name: idx_16576047_fk.import_export_log.invalid_records_log_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576047_fk.import_export_log.invalid_records_log_id" ON public.import_export_log USING btree (invalid_records_log_id);


--
-- Name: idx_16576047_fk.import_export_log.profile_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576047_fk.import_export_log.profile_id" ON public.import_export_log USING btree (profile_id);


--
-- Name: idx_16576047_fk.import_export_log.user_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576047_fk.import_export_log.user_id" ON public.import_export_log USING btree (user_id);


--
-- Name: idx_16576057_uniq.import_export_profile.technical_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576057_uniq.import_export_profile.technical_name" ON public.import_export_profile USING btree (technical_name);


--
-- Name: idx_16576071_fk.import_export_profile_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576071_fk.import_export_profile_translation.language_id" ON public.import_export_profile_translation USING btree (language_id);


--
-- Name: idx_16576090_idx.access_key; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576090_idx.access_key" ON public.integration USING btree (access_key);


--
-- Name: idx_16576104_fk.integration_acl_role.acl_role_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576104_fk.integration_acl_role.acl_role_id" ON public.integration_role USING btree (acl_role_id);


--
-- Name: idx_16576111_tag; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_16576111_tag ON public.invalidation_tags USING btree (tag);


--
-- Name: idx_16576118_fk.landing_page.cms_page_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576118_fk.landing_page.cms_page_id" ON public.landing_page USING btree (cms_page_id, cms_page_version_id);


--
-- Name: idx_16576130_fk.landing_page_sales_channel.sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576130_fk.landing_page_sales_channel.sales_channel_id" ON public.landing_page_sales_channel USING btree (sales_channel_id);


--
-- Name: idx_16576138_fk.landing_page_tag.tag_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576138_fk.landing_page_tag.tag_id" ON public.landing_page_tag USING btree (tag_id);


--
-- Name: idx_16576146_fk.landing_page_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576146_fk.landing_page_translation.language_id" ON public.landing_page_translation USING btree (language_id);


--
-- Name: idx_16576155_fk.language.locale_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576155_fk.language.locale_id" ON public.language USING btree (locale_id);


--
-- Name: idx_16576155_fk.language.parent_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576155_fk.language.parent_id" ON public.language USING btree (parent_id);


--
-- Name: idx_16576155_idx.language.language_id_parent_language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576155_idx.language.language_id_parent_language_id" ON public.language USING btree (id, parent_id);


--
-- Name: idx_16576155_idx.language.translation_code_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576155_idx.language.translation_code_id" ON public.language USING btree (translation_code_id);


--
-- Name: idx_16576168_uniq.code; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576168_uniq.code" ON public.locale USING btree (code);


--
-- Name: idx_16576176_fk.locale_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576176_fk.locale_translation.language_id" ON public.locale_translation USING btree (language_id);


--
-- Name: idx_16576184_idx.log_entry.created_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576184_idx.log_entry.created_at" ON public.log_entry USING btree (created_at);


--
-- Name: idx_16576203_fk.mail_header_footer_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576203_fk.mail_header_footer_translation.language_id" ON public.mail_header_footer_translation USING btree (language_id);


--
-- Name: idx_16576211_fk.mail_template.mail_template_type_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576211_fk.mail_template.mail_template_type_id" ON public.mail_template USING btree (mail_template_type_id);


--
-- Name: idx_16576222_fk.mail_template_media.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576222_fk.mail_template_media.language_id" ON public.mail_template_media USING btree (language_id);


--
-- Name: idx_16576222_fk.mail_template_media.mail_template_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576222_fk.mail_template_media.mail_template_id" ON public.mail_template_media USING btree (mail_template_id);


--
-- Name: idx_16576222_fk.mail_template_media.media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576222_fk.mail_template_media.media_id" ON public.mail_template_media USING btree (media_id);


--
-- Name: idx_16576232_fk.mail_template_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576232_fk.mail_template_translation.language_id" ON public.mail_template_translation USING btree (language_id);


--
-- Name: idx_16576240_uniq.mail_template_type.technical_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576240_uniq.mail_template_type.technical_name" ON public.mail_template_type USING btree (technical_name);


--
-- Name: idx_16576248_fk.mail_template_type_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576248_fk.mail_template_type_translation.language_id" ON public.mail_template_type_translation USING btree (language_id);


--
-- Name: idx_16576256_fk.main_category.category_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576256_fk.main_category.category_id" ON public.main_category USING btree (category_id, category_version_id);


--
-- Name: idx_16576256_fk.main_category.sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576256_fk.main_category.sales_channel_id" ON public.main_category USING btree (sales_channel_id);


--
-- Name: idx_16576256_uniq.main_category.sales_channel_product; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576256_uniq.main_category.sales_channel_product" ON public.main_category USING btree (product_id, product_version_id, sales_channel_id);


--
-- Name: idx_16576268_idx_mcp_tool_result_session; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_16576268_idx_mcp_tool_result_session ON public.mcp_tool_result_cache USING btree (session_id);


--
-- Name: idx_16576279_idx_mcp_toolset_session; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_16576279_idx_mcp_toolset_session ON public.mcp_toolset_session USING btree (session_id);


--
-- Name: idx_16576287_fk.measurement_display_unit.measurement_system_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576287_fk.measurement_display_unit.measurement_system_id" ON public.measurement_display_unit USING btree (measurement_system_id);


--
-- Name: idx_16576287_uniq.measurement_display_unit.short_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576287_uniq.measurement_display_unit.short_name" ON public.measurement_display_unit USING btree (short_name);


--
-- Name: idx_16576302_fk.measurement_display_unit_translation.language_i; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576302_fk.measurement_display_unit_translation.language_i" ON public.measurement_display_unit_translation USING btree (language_id);


--
-- Name: idx_16576310_uniq.measurement_system.technical_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576310_uniq.measurement_system.technical_name" ON public.measurement_system USING btree (technical_name);


--
-- Name: idx_16576318_fk.measurement_system_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576318_fk.measurement_system_translation.language_id" ON public.measurement_system_translation USING btree (language_id);


--
-- Name: idx_16576326_fk.media.user_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576326_fk.media.user_id" ON public.media USING btree (user_id);


--
-- Name: idx_16576326_idx.media.file_extension; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576326_idx.media.file_extension" ON public.media USING btree (file_extension);


--
-- Name: idx_16576326_idx.media.file_hash; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576326_idx.media.file_hash" ON public.media USING btree (file_hash);


--
-- Name: idx_16576326_idx.media.file_name; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576326_idx.media.file_name" ON public.media USING btree (file_name);


--
-- Name: idx_16576326_idx.media.media_folder_id_created_at_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576326_idx.media.media_folder_id_created_at_id" ON public.media USING btree (media_folder_id, created_at, id);


--
-- Name: idx_16576326_idx.media.uploaded_at_created_at_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576326_idx.media.uploaded_at_created_at_id" ON public.media USING btree (uploaded_at, created_at, id);


--
-- Name: idx_16576335_uniq.media_default_folder.entity; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576335_uniq.media_default_folder.entity" ON public.media_default_folder USING btree (entity);


--
-- Name: idx_16576343_fk.media_folder.parent_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576343_fk.media_folder.parent_id" ON public.media_folder USING btree (parent_id);


--
-- Name: idx_16576343_uniq.media_folder.default_folder_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576343_uniq.media_folder.default_folder_id" ON public.media_folder USING btree (default_folder_id);


--
-- Name: idx_16576364_fk.media_folder_configuration_media_thumbnail_size; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576364_fk.media_folder_configuration_media_thumbnail_size" ON public.media_folder_configuration_media_thumbnail_size USING btree (media_thumbnail_size_id);


--
-- Name: idx_16576371_fk.media_tag.tag_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576371_fk.media_tag.tag_id" ON public.media_tag USING btree (tag_id);


--
-- Name: idx_16576378_fk.media_thumbnail.media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576378_fk.media_thumbnail.media_id" ON public.media_thumbnail USING btree (media_id);


--
-- Name: idx_16576378_fk.media_thumbnail.media_thumbnail_size_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576378_fk.media_thumbnail.media_thumbnail_size_id" ON public.media_thumbnail USING btree (media_thumbnail_size_id);


--
-- Name: idx_16576388_uniq.width; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576388_uniq.width" ON public.media_thumbnail_size USING btree (width, height);


--
-- Name: idx_16576397_fk.media_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576397_fk.media_translation.language_id" ON public.media_translation USING btree (language_id);


--
-- Name: idx_16576406_available_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_16576406_available_at ON public.messenger_messages USING btree (available_at);


--
-- Name: idx_16576406_delivered_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_16576406_delivered_at ON public.messenger_messages USING btree (delivered_at);


--
-- Name: idx_16576406_queue_name; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_16576406_queue_name ON public.messenger_messages USING btree (queue_name);


--
-- Name: idx_16576419_idx_created_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_16576419_idx_created_at ON public.messenger_stats USING btree (created_at);


--
-- Name: idx_16576434_fk.newsletter_recipient.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576434_fk.newsletter_recipient.language_id" ON public.newsletter_recipient USING btree (language_id);


--
-- Name: idx_16576434_fk.newsletter_recipient.sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576434_fk.newsletter_recipient.sales_channel_id" ON public.newsletter_recipient USING btree (sales_channel_id);


--
-- Name: idx_16576434_fk.newsletter_recipient.salutation_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576434_fk.newsletter_recipient.salutation_id" ON public.newsletter_recipient USING btree (salutation_id);


--
-- Name: idx_16576434_hash; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_16576434_hash ON public.newsletter_recipient USING btree (hash);


--
-- Name: idx_16576434_idx.newsletter_recipient.email; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576434_idx.newsletter_recipient.email" ON public.newsletter_recipient USING btree (email);


--
-- Name: idx_16576446_fk.newsletter_recipient_tag.tag_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576446_fk.newsletter_recipient_tag.tag_id" ON public.newsletter_recipient_tag USING btree (tag_id);


--
-- Name: idx_16576453_fk.notification.created_by_integration_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576453_fk.notification.created_by_integration_id" ON public.notification USING btree (created_by_integration_id);


--
-- Name: idx_16576453_fk.notification.created_by_user_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576453_fk.notification.created_by_user_id" ON public.notification USING btree (created_by_user_id);


--
-- Name: idx_16576475_fk.number_range_sales_channel.number_range_type_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576475_fk.number_range_sales_channel.number_range_type_id" ON public.number_range_sales_channel USING btree (number_range_type_id);


--
-- Name: idx_16576475_fk.number_range_sales_channel.sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576475_fk.number_range_sales_channel.sales_channel_id" ON public.number_range_sales_channel USING btree (sales_channel_id);


--
-- Name: idx_16576475_uniq.numer_range_id__sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576475_uniq.numer_range_id__sales_channel_id" ON public.number_range_sales_channel USING btree (number_range_id, sales_channel_id);


--
-- Name: idx_16576484_idx.number_range_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576484_idx.number_range_id" ON public.number_range_state USING btree (number_range_id);


--
-- Name: idx_16576484_uniq.id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576484_uniq.id" ON public.number_range_state USING btree (id);


--
-- Name: idx_16576493_fk.number_range_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576493_fk.number_range_translation.language_id" ON public.number_range_translation USING btree (language_id);


--
-- Name: idx_16576501_uniq.technical_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576501_uniq.technical_name" ON public.number_range_type USING btree (technical_name);


--
-- Name: idx_16576509_fk.number_range_type_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576509_fk.number_range_type_translation.language_id" ON public.number_range_type_translation USING btree (language_id);


--
-- Name: idx_16576517_uniq.oauth_auth_code.code_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576517_uniq.oauth_auth_code.code_id" ON public.oauth_auth_code USING btree (code_id);


--
-- Name: idx_16576528_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_16576528_id ON public.oauth_user USING btree (id);


--
-- Name: idx_16576528_idx.oauth_user.user_sub; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576528_idx.oauth_user.user_sub" ON public.oauth_user USING btree (user_sub);


--
-- Name: idx_16576528_user_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_16576528_user_id ON public.oauth_user USING btree (user_id);


--
-- Name: idx_16576528_user_sub; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_16576528_user_sub ON public.oauth_user USING btree (user_sub);


--
-- Name: idx_16576539_fk.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576539_fk.language_id" ON public."order" USING btree (language_id);


--
-- Name: idx_16576539_fk.order.created_by_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576539_fk.order.created_by_id" ON public."order" USING btree (created_by_id);


--
-- Name: idx_16576539_fk.order.currency_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576539_fk.order.currency_id" ON public."order" USING btree (currency_id);


--
-- Name: idx_16576539_fk.order.sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576539_fk.order.sales_channel_id" ON public."order" USING btree (sales_channel_id);


--
-- Name: idx_16576539_fk.order.updated_by_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576539_fk.order.updated_by_id" ON public."order" USING btree (updated_by_id);


--
-- Name: idx_16576539_idx.order_date_currency_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576539_idx.order_date_currency_id" ON public."order" USING btree (order_date, currency_id);


--
-- Name: idx_16576539_idx.order_number; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576539_idx.order_number" ON public."order" USING btree (order_number);


--
-- Name: idx_16576539_idx.state_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576539_idx.state_index" ON public."order" USING btree (state_id);


--
-- Name: idx_16576539_uidx.order.primary_order_delivery; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576539_uidx.order.primary_order_delivery" ON public."order" USING btree (id, version_id, primary_order_delivery_id);


--
-- Name: idx_16576539_uidx.order.primary_order_transaction; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576539_uidx.order.primary_order_transaction" ON public."order" USING btree (id, version_id, primary_order_transaction_id);


--
-- Name: idx_16576539_uniq.auto_increment; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576539_uniq.auto_increment" ON public."order" USING btree (auto_increment);


--
-- Name: idx_16576539_uniq.deep_link_code; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576539_uniq.deep_link_code" ON public."order" USING btree (deep_link_code, version_id);


--
-- Name: idx_16576558_fk.order_address.country_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576558_fk.order_address.country_id" ON public.order_address USING btree (country_id);


--
-- Name: idx_16576558_fk.order_address.country_state_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576558_fk.order_address.country_state_id" ON public.order_address USING btree (country_state_id);


--
-- Name: idx_16576558_fk.order_address.order_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576558_fk.order_address.order_id" ON public.order_address USING btree (order_id, order_version_id);


--
-- Name: idx_16576558_fk.order_address.salutation_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576558_fk.order_address.salutation_id" ON public.order_address USING btree (salutation_id);


--
-- Name: idx_16576558_idx.order_address_created_updated; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576558_idx.order_address_created_updated" ON public.order_address USING btree (created_at, updated_at);


--
-- Name: idx_16576573_fk.order_customer.customer_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576573_fk.order_customer.customer_id" ON public.order_customer USING btree (customer_id);


--
-- Name: idx_16576573_fk.order_customer.order_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576573_fk.order_customer.order_id" ON public.order_customer USING btree (order_id, order_version_id);


--
-- Name: idx_16576573_fk.order_customer.salutation_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576573_fk.order_customer.salutation_id" ON public.order_customer USING btree (salutation_id);


--
-- Name: idx_16576586_fk.order_delivery.order_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576586_fk.order_delivery.order_id" ON public.order_delivery USING btree (order_id, order_version_id);


--
-- Name: idx_16576586_fk.order_delivery.shipping_method_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576586_fk.order_delivery.shipping_method_id" ON public.order_delivery USING btree (shipping_method_id);


--
-- Name: idx_16576586_fk.order_delivery.shipping_order_address_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576586_fk.order_delivery.shipping_order_address_id" ON public.order_delivery USING btree (shipping_order_address_id, shipping_order_address_version_id);


--
-- Name: idx_16576586_idx.state_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576586_idx.state_index" ON public.order_delivery USING btree (state_id);


--
-- Name: idx_16576602_fk.order_delivery_position.order_delivery_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576602_fk.order_delivery_position.order_delivery_id" ON public.order_delivery_position USING btree (order_delivery_id, order_delivery_version_id);


--
-- Name: idx_16576602_fk.order_delivery_position.order_line_item_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576602_fk.order_delivery_position.order_line_item_id" ON public.order_delivery_position USING btree (order_line_item_id, order_line_item_version_id);


--
-- Name: idx_16576615_fk.order_line_item.cover_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576615_fk.order_line_item.cover_id" ON public.order_line_item USING btree (cover_id);


--
-- Name: idx_16576615_fk.order_line_item.order_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576615_fk.order_line_item.order_id" ON public.order_line_item USING btree (order_id, order_version_id);


--
-- Name: idx_16576615_fk.order_line_item.parent_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576615_fk.order_line_item.parent_id" ON public.order_line_item USING btree (parent_id, parent_version_id);


--
-- Name: idx_16576615_fk.order_line_item.product; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576615_fk.order_line_item.product" ON public.order_line_item USING btree (product_id, product_version_id);


--
-- Name: idx_16576615_idx.order_line_item.promotion_redemption; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576615_idx.order_line_item.promotion_redemption" ON public.order_line_item USING btree (promotion_id, version_id, order_id, order_version_id);


--
-- Name: idx_16576615_idx.order_line_item_created_updated; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576615_idx.order_line_item_created_updated" ON public.order_line_item USING btree (created_at, updated_at);


--
-- Name: idx_16576637_fk.order_line_item_download.media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576637_fk.order_line_item_download.media_id" ON public.order_line_item_download USING btree (media_id);


--
-- Name: idx_16576637_fk.order_line_item_download.order_line_item_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576637_fk.order_line_item_download.order_line_item_id" ON public.order_line_item_download USING btree (order_line_item_id, order_line_item_version_id);


--
-- Name: idx_16576652_fk.order_tag.tag_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576652_fk.order_tag.tag_id" ON public.order_tag USING btree (tag_id);


--
-- Name: idx_16576660_fk.order_transaction.order_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576660_fk.order_transaction.order_id" ON public.order_transaction USING btree (order_id, order_version_id);


--
-- Name: idx_16576660_fk.order_transaction.payment_method_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576660_fk.order_transaction.payment_method_id" ON public.order_transaction USING btree (payment_method_id);


--
-- Name: idx_16576660_idx.order_transaction_created_updated; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576660_idx.order_transaction_created_updated" ON public.order_transaction USING btree (created_at, updated_at);


--
-- Name: idx_16576660_idx.state_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576660_idx.state_index" ON public.order_transaction USING btree (state_id);


--
-- Name: idx_16576673_fk.order_transaction_capture.order_transaction_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576673_fk.order_transaction_capture.order_transaction_id" ON public.order_transaction_capture USING btree (order_transaction_id, order_transaction_version_id);


--
-- Name: idx_16576673_fk.order_transaction_capture.state_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576673_fk.order_transaction_capture.state_id" ON public.order_transaction_capture USING btree (state_id);


--
-- Name: idx_16576686_fk.order_transaction_capture_refund.capture_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576686_fk.order_transaction_capture_refund.capture_id" ON public.order_transaction_capture_refund USING btree (capture_id, capture_version_id);


--
-- Name: idx_16576686_fk.order_transaction_capture_refund.state_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576686_fk.order_transaction_capture_refund.state_id" ON public.order_transaction_capture_refund USING btree (state_id);


--
-- Name: idx_16576699_fk.order_transaction_capture_refund_position.order; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576699_fk.order_transaction_capture_refund_position.order" ON public.order_transaction_capture_refund_position USING btree (order_line_item_id, order_line_item_version_id);


--
-- Name: idx_16576699_fk.order_transaction_capture_refund_position.refun; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576699_fk.order_transaction_capture_refund_position.refun" ON public.order_transaction_capture_refund_position USING btree (refund_id, refund_version_id);


--
-- Name: idx_16576714_fk.payment_method.availability_rule_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576714_fk.payment_method.availability_rule_id" ON public.payment_method USING btree (availability_rule_id);


--
-- Name: idx_16576714_fk.payment_method.media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576714_fk.payment_method.media_id" ON public.payment_method USING btree (media_id);


--
-- Name: idx_16576714_fk.payment_method.plugin_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576714_fk.payment_method.plugin_id" ON public.payment_method USING btree (plugin_id);


--
-- Name: idx_16576714_uniq.technical_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576714_uniq.technical_name" ON public.payment_method USING btree (technical_name);


--
-- Name: idx_16576730_fk.payment_method_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576730_fk.payment_method_translation.language_id" ON public.payment_method_translation USING btree (language_id);


--
-- Name: idx_16576744_uniq.baseClass; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576744_uniq.baseClass" ON public.plugin USING btree (base_class);


--
-- Name: idx_16576744_uniq.name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576744_uniq.name" ON public.plugin USING btree (name);


--
-- Name: idx_16576759_fk.plugin_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576759_fk.plugin_translation.language_id" ON public.plugin_translation USING btree (language_id);


--
-- Name: idx_16576768_auto_increment; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_16576768_auto_increment ON public.product USING btree (auto_increment);


--
-- Name: idx_16576768_fk.product.canonical_product_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576768_fk.product.canonical_product_id" ON public.product USING btree (canonical_product_id, canonical_product_version_id);


--
-- Name: idx_16576768_fk.product.cms_page_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576768_fk.product.cms_page_id" ON public.product USING btree (cms_page_id, cms_page_version_id);


--
-- Name: idx_16576768_fk.product.feature_set_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576768_fk.product.feature_set_id" ON public.product USING btree (product_feature_set_id);


--
-- Name: idx_16576768_fk.product.open_graph_media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576768_fk.product.open_graph_media_id" ON public.product USING btree (open_graph_media_id);


--
-- Name: idx_16576768_fk.product.parent_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576768_fk.product.parent_id" ON public.product USING btree (parent_id, parent_version_id);


--
-- Name: idx_16576768_fk.product.product_manufacturer; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576768_fk.product.product_manufacturer" ON public.product USING btree (product_manufacturer_id, product_manufacturer_version_id);


--
-- Name: idx_16576768_fk.product.product_media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576768_fk.product.product_media_id" ON public.product USING btree (product_media_id, product_media_version_id);


--
-- Name: idx_16576768_fk.product.tax_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576768_fk.product.tax_id" ON public.product USING btree (tax_id);


--
-- Name: idx_16576768_fk.product.unit_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576768_fk.product.unit_id" ON public.product USING btree (unit_id);


--
-- Name: idx_16576768_idx.product.categories; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576768_idx.product.categories" ON public.product USING btree (categories);


--
-- Name: idx_16576768_idx.product.type; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576768_idx.product.type" ON public.product USING btree (type);


--
-- Name: idx_16576768_uniq.product.product_number__version_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576768_uniq.product.product_number__version_id" ON public.product USING btree (product_number, version_id);


--
-- Name: idx_16576786_fk.product_category.category_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576786_fk.product_category.category_id" ON public.product_category USING btree (category_id, category_version_id);


--
-- Name: idx_16576795_fk.product_category_tree.category_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576795_fk.product_category_tree.category_id" ON public.product_category_tree USING btree (category_id, category_version_id);


--
-- Name: idx_16576804_fk.product_configurator_setting.media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576804_fk.product_configurator_setting.media_id" ON public.product_configurator_setting USING btree (media_id);


--
-- Name: idx_16576804_fk.product_configurator_setting.product_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576804_fk.product_configurator_setting.product_id" ON public.product_configurator_setting USING btree (product_id, product_version_id);


--
-- Name: idx_16576804_fk.product_configurator_setting.property_group_opt; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576804_fk.product_configurator_setting.property_group_opt" ON public.product_configurator_setting USING btree (property_group_option_id);


--
-- Name: idx_16576804_uniq.product_configurator_setting.prod_id.vers_id.; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576804_uniq.product_configurator_setting.prod_id.vers_id." ON public.product_configurator_setting USING btree (product_id, version_id, property_group_option_id);


--
-- Name: idx_16576817_fk.product_cross_selling.product_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576817_fk.product_cross_selling.product_id" ON public.product_cross_selling USING btree (product_id, product_version_id);


--
-- Name: idx_16576817_fk.product_cross_selling.product_stream_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576817_fk.product_cross_selling.product_stream_id" ON public.product_cross_selling USING btree (product_stream_id);


--
-- Name: idx_16576833_fk.product_cross_selling_assigned_products.cross_s; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576833_fk.product_cross_selling_assigned_products.cross_s" ON public.product_cross_selling_assigned_products USING btree (cross_selling_id);


--
-- Name: idx_16576833_product_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_16576833_product_id ON public.product_cross_selling_assigned_products USING btree (product_id, product_version_id);


--
-- Name: idx_16576833_uniq.cross_selling_id__product_id__product_version; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576833_uniq.cross_selling_id__product_id__product_version" ON public.product_cross_selling_assigned_products USING btree (cross_selling_id, product_id, product_version_id);


--
-- Name: idx_16576845_fk.product_cross_selling_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576845_fk.product_cross_selling_translation.language_id" ON public.product_cross_selling_translation USING btree (language_id);


--
-- Name: idx_16576853_fk.product_custom_field_set.product_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576853_fk.product_custom_field_set.product_id" ON public.product_custom_field_set USING btree (product_id, product_version_id);


--
-- Name: idx_16576861_fk.product_download.media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576861_fk.product_download.media_id" ON public.product_download USING btree (media_id);


--
-- Name: idx_16576861_fk.product_download.product_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576861_fk.product_download.product_id" ON public.product_download USING btree (product_id, product_version_id);


--
-- Name: idx_16576874_file_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_16576874_file_name ON public.product_export USING btree (file_name);


--
-- Name: idx_16576874_fk.product_export.product_stream_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576874_fk.product_export.product_stream_id" ON public.product_export USING btree (product_stream_id);


--
-- Name: idx_16576874_fk.product_export.sales_channel_domain_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576874_fk.product_export.sales_channel_domain_id" ON public.product_export USING btree (sales_channel_domain_id);


--
-- Name: idx_16576874_fk.product_export.sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576874_fk.product_export.sales_channel_id" ON public.product_export USING btree (sales_channel_id);


--
-- Name: idx_16576874_fk.product_export.storefront_sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576874_fk.product_export.storefront_sales_channel_id" ON public.product_export USING btree (storefront_sales_channel_id);


--
-- Name: idx_16576902_fk.product_feature_set_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576902_fk.product_feature_set_translation.language_id" ON public.product_feature_set_translation USING btree (language_id);


--
-- Name: idx_16576910_idx.product_keyword_dictionary.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576910_idx.product_keyword_dictionary.language_id" ON public.product_keyword_dictionary USING btree (language_id);


--
-- Name: idx_16576910_uniq.language_id_keyword; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576910_uniq.language_id_keyword" ON public.product_keyword_dictionary USING btree (language_id, keyword);


--
-- Name: idx_16576918_fk.product_manufacturer.media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576918_fk.product_manufacturer.media_id" ON public.product_manufacturer USING btree (media_id);


--
-- Name: idx_16576926_fk.product_manufacturer_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576926_fk.product_manufacturer_translation.language_id" ON public.product_manufacturer_translation USING btree (language_id);


--
-- Name: idx_16576935_fk.product_media.media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576935_fk.product_media.media_id" ON public.product_media USING btree (media_id);


--
-- Name: idx_16576935_fk.product_media.product_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576935_fk.product_media.product_id" ON public.product_media USING btree (product_id, product_version_id);


--
-- Name: idx_16576948_fk.product_option.property_group_option_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576948_fk.product_option.property_group_option_id" ON public.product_option USING btree (property_group_option_id);


--
-- Name: idx_16576956_fk.product_price.product_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576956_fk.product_price.product_id" ON public.product_price USING btree (product_id, product_version_id);


--
-- Name: idx_16576956_fk.product_price.rule_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576956_fk.product_price.rule_id" ON public.product_price USING btree (rule_id);


--
-- Name: idx_16576969_fk.product_property.configuration_option_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576969_fk.product_property.configuration_option_id" ON public.product_property USING btree (property_group_option_id);


--
-- Name: idx_16576977_fk.product_review.customer_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576977_fk.product_review.customer_id" ON public.product_review USING btree (customer_id);


--
-- Name: idx_16576977_fk.product_review.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576977_fk.product_review.language_id" ON public.product_review USING btree (language_id);


--
-- Name: idx_16576977_fk.product_review.product_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576977_fk.product_review.product_id" ON public.product_review USING btree (product_id, product_version_id);


--
-- Name: idx_16576977_fk.product_review.sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576977_fk.product_review.sales_channel_id" ON public.product_review USING btree (sales_channel_id);


--
-- Name: idx_16576987_uniq.product_search_config.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576987_uniq.product_search_config.language_id" ON public.product_search_config USING btree (language_id);


--
-- Name: idx_16576999_fk.search_config_field.custom_field_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576999_fk.search_config_field.custom_field_id" ON public.product_search_config_field USING btree (custom_field_id);


--
-- Name: idx_16576999_fk.search_config_field.product_search_config_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16576999_fk.search_config_field.product_search_config_id" ON public.product_search_config_field USING btree (product_search_config_id);


--
-- Name: idx_16576999_uniq.search_config_field.field__config_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16576999_uniq.search_config_field.field__config_id" ON public.product_search_config_field USING btree (field, product_search_config_id);


--
-- Name: idx_16577016_idx.product_search_keyword.keyword_language; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577016_idx.product_search_keyword.keyword_language" ON public.product_search_keyword USING btree (keyword, language_id);


--
-- Name: idx_16577016_idx.product_search_keyword.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577016_idx.product_search_keyword.language_id" ON public.product_search_keyword USING btree (language_id);


--
-- Name: idx_16577016_idx.product_search_keyword.product_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577016_idx.product_search_keyword.product_id" ON public.product_search_keyword USING btree (product_id, product_version_id);


--
-- Name: idx_16577029_uniq.product_sorting.url_key; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577029_uniq.product_sorting.url_key" ON public.product_sorting USING btree (url_key);


--
-- Name: idx_16577043_fk.product_sorting_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577043_fk.product_sorting_translation.language_id" ON public.product_sorting_translation USING btree (language_id);


--
-- Name: idx_16577064_fk.product_stream_filter.parent_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577064_fk.product_stream_filter.parent_id" ON public.product_stream_filter USING btree (parent_id);


--
-- Name: idx_16577064_fk.product_stream_filter.product_stream_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577064_fk.product_stream_filter.product_stream_id" ON public.product_stream_filter USING btree (product_stream_id);


--
-- Name: idx_16577075_fk.product_stream_mapping.product_stream_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577075_fk.product_stream_mapping.product_stream_id" ON public.product_stream_mapping USING btree (product_stream_id);


--
-- Name: idx_16577083_fk.product_stream_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577083_fk.product_stream_translation.language_id" ON public.product_stream_translation USING btree (language_id);


--
-- Name: idx_16577091_fk.product_tag.tag_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577091_fk.product_tag.tag_id" ON public.product_tag USING btree (tag_id);


--
-- Name: idx_16577099_fk.product_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577099_fk.product_translation.language_id" ON public.product_translation USING btree (language_id);


--
-- Name: idx_16577108_idx.product_visibility.product_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577108_idx.product_visibility.product_id" ON public.product_visibility USING btree (product_id, product_version_id);


--
-- Name: idx_16577108_idx.product_visibility.sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577108_idx.product_visibility.sales_channel_id" ON public.product_visibility USING btree (sales_channel_id);


--
-- Name: idx_16577108_uniq.product_id__sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577108_uniq.product_id__sales_channel_id" ON public.product_visibility USING btree (product_id, product_version_id, sales_channel_id);


--
-- Name: idx_16577119_code; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_16577119_code ON public.promotion USING btree (code);


--
-- Name: idx_16577119_individual_code_pattern; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_16577119_individual_code_pattern ON public.promotion USING btree (individual_code_pattern);


--
-- Name: idx_16577144_fk.promotion_cart_rule.rule_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577144_fk.promotion_cart_rule.rule_id" ON public.promotion_cart_rule USING btree (rule_id);


--
-- Name: idx_16577151_idx.promotion_discount.promotion_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577151_idx.promotion_discount.promotion_id" ON public.promotion_discount USING btree (promotion_id);


--
-- Name: idx_16577164_fk.promotion_discount_prices.currency_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577164_fk.promotion_discount_prices.currency_id" ON public.promotion_discount_prices USING btree (currency_id);


--
-- Name: idx_16577164_fk.promotion_discount_prices.discount_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577164_fk.promotion_discount_prices.discount_id" ON public.promotion_discount_prices USING btree (discount_id);


--
-- Name: idx_16577175_fk.promotion_discount_rule.rule_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577175_fk.promotion_discount_rule.rule_id" ON public.promotion_discount_rule USING btree (rule_id);


--
-- Name: idx_16577182_code; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_16577182_code ON public.promotion_individual_code USING btree (code);


--
-- Name: idx_16577182_idx.promotion_individual_code.promotion_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577182_idx.promotion_individual_code.promotion_id" ON public.promotion_individual_code USING btree (promotion_id);


--
-- Name: idx_16577190_fk.promotion_order_rule.rule_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577190_fk.promotion_order_rule.rule_id" ON public.promotion_order_rule USING btree (rule_id);


--
-- Name: idx_16577197_fk.promotion_persona_customer.customer_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577197_fk.promotion_persona_customer.customer_id" ON public.promotion_persona_customer USING btree (customer_id);


--
-- Name: idx_16577204_fk.promotion_persona_rule.rule_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577204_fk.promotion_persona_rule.rule_id" ON public.promotion_persona_rule USING btree (rule_id);


--
-- Name: idx_16577211_idx.promotion_sales_channel.promotion_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577211_idx.promotion_sales_channel.promotion_id" ON public.promotion_sales_channel USING btree (promotion_id);


--
-- Name: idx_16577211_idx.promotion_sales_channel.sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577211_idx.promotion_sales_channel.sales_channel_id" ON public.promotion_sales_channel USING btree (sales_channel_id);


--
-- Name: idx_16577222_idx.promotion_setgroup.promotion_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577222_idx.promotion_setgroup.promotion_id" ON public.promotion_setgroup USING btree (promotion_id);


--
-- Name: idx_16577233_fk.promotion_setgroup_rule.rule_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577233_fk.promotion_setgroup_rule.rule_id" ON public.promotion_setgroup_rule USING btree (rule_id);


--
-- Name: idx_16577240_fk.promotion_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577240_fk.promotion_translation.language_id" ON public.promotion_translation USING btree (language_id);


--
-- Name: idx_16577240_fk.promotion_translation.promotion_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577240_fk.promotion_translation.promotion_id" ON public.promotion_translation USING btree (promotion_id);


--
-- Name: idx_16577262_fk.property_group_option.media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577262_fk.property_group_option.media_id" ON public.property_group_option USING btree (media_id);


--
-- Name: idx_16577262_fk.property_group_option.property_group_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577262_fk.property_group_option.property_group_id" ON public.property_group_option USING btree (property_group_id);


--
-- Name: idx_16577270_fk.property_group_option_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577270_fk.property_group_option_translation.language_id" ON public.property_group_option_translation USING btree (language_id);


--
-- Name: idx_16577280_fk.property_group_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577280_fk.property_group_translation.language_id" ON public.property_group_translation USING btree (language_id);


--
-- Name: idx_16577289_idx.refresh_token.family_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577289_idx.refresh_token.family_id" ON public.refresh_token USING btree (family_id);


--
-- Name: idx_16577289_uniq.token_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577289_uniq.token_id" ON public.refresh_token USING btree (token_id);


--
-- Name: idx_16577310_fk.rule_condition.parent_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577310_fk.rule_condition.parent_id" ON public.rule_condition USING btree (parent_id);


--
-- Name: idx_16577310_fk.rule_condition.rule_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577310_fk.rule_condition.rule_id" ON public.rule_condition USING btree (rule_id);


--
-- Name: idx_16577310_fk.rule_condition.script_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577310_fk.rule_condition.script_id" ON public.rule_condition USING btree (script_id);


--
-- Name: idx_16577321_fk.rule_tag.tag_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577321_fk.rule_tag.tag_id" ON public.rule_tag USING btree (tag_id);


--
-- Name: idx_16577328_fk.sales_channel.analytics_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577328_fk.sales_channel.analytics_id" ON public.sales_channel USING btree (analytics_id);


--
-- Name: idx_16577328_fk.sales_channel.country_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577328_fk.sales_channel.country_id" ON public.sales_channel USING btree (country_id);


--
-- Name: idx_16577328_fk.sales_channel.currency_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577328_fk.sales_channel.currency_id" ON public.sales_channel USING btree (currency_id);


--
-- Name: idx_16577328_fk.sales_channel.customer_group_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577328_fk.sales_channel.customer_group_id" ON public.sales_channel USING btree (customer_group_id);


--
-- Name: idx_16577328_fk.sales_channel.footer_category_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577328_fk.sales_channel.footer_category_id" ON public.sales_channel USING btree (footer_category_id, footer_category_version_id);


--
-- Name: idx_16577328_fk.sales_channel.header_footer_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577328_fk.sales_channel.header_footer_id" ON public.sales_channel USING btree (mail_header_footer_id);


--
-- Name: idx_16577328_fk.sales_channel.home_cms_page; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577328_fk.sales_channel.home_cms_page" ON public.sales_channel USING btree (home_cms_page_id, home_cms_page_version_id);


--
-- Name: idx_16577328_fk.sales_channel.hreflang_default_domain_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577328_fk.sales_channel.hreflang_default_domain_id" ON public.sales_channel USING btree (hreflang_default_domain_id);


--
-- Name: idx_16577328_fk.sales_channel.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577328_fk.sales_channel.language_id" ON public.sales_channel USING btree (language_id);


--
-- Name: idx_16577328_fk.sales_channel.navigation_category_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577328_fk.sales_channel.navigation_category_id" ON public.sales_channel USING btree (navigation_category_id, navigation_category_version_id);


--
-- Name: idx_16577328_fk.sales_channel.payment_method_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577328_fk.sales_channel.payment_method_id" ON public.sales_channel USING btree (payment_method_id);


--
-- Name: idx_16577328_fk.sales_channel.service_category_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577328_fk.sales_channel.service_category_id" ON public.sales_channel USING btree (service_category_id, service_category_version_id);


--
-- Name: idx_16577328_fk.sales_channel.shipping_method_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577328_fk.sales_channel.shipping_method_id" ON public.sales_channel USING btree (shipping_method_id);


--
-- Name: idx_16577328_fk.sales_channel.type_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577328_fk.sales_channel.type_id" ON public.sales_channel USING btree (type_id);


--
-- Name: idx_16577328_uniq.access_key; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577328_uniq.access_key" ON public.sales_channel USING btree (access_key);


--
-- Name: idx_16577372_fk.sales_channel_api_context.customer_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577372_fk.sales_channel_api_context.customer_id" ON public.sales_channel_api_context USING btree (customer_id);


--
-- Name: idx_16577372_uniq.sales_channel_api_context.sales_channel_id_cu; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577372_uniq.sales_channel_api_context.sales_channel_id_cu" ON public.sales_channel_api_context USING btree (sales_channel_id, customer_id);


--
-- Name: idx_16577379_fk.sales_channel_country.country_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577379_fk.sales_channel_country.country_id" ON public.sales_channel_country USING btree (country_id);


--
-- Name: idx_16577386_fk.sales_channel_currency.currency_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577386_fk.sales_channel_currency.currency_id" ON public.sales_channel_currency USING btree (currency_id);


--
-- Name: idx_16577393_fk.sales_channel_domain.currency_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577393_fk.sales_channel_domain.currency_id" ON public.sales_channel_domain USING btree (currency_id);


--
-- Name: idx_16577393_fk.sales_channel_domain.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577393_fk.sales_channel_domain.language_id" ON public.sales_channel_domain USING btree (language_id);


--
-- Name: idx_16577393_fk.sales_channel_domain.sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577393_fk.sales_channel_domain.sales_channel_id" ON public.sales_channel_domain USING btree (sales_channel_id);


--
-- Name: idx_16577393_fk.sales_channel_domain.snippet_set_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577393_fk.sales_channel_domain.snippet_set_id" ON public.sales_channel_domain USING btree (snippet_set_id);


--
-- Name: idx_16577393_uniq.sales_channel_domain.external_storefront; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577393_uniq.sales_channel_domain.external_storefront" ON public.sales_channel_domain USING btree (external_storefront_language_id, sales_channel_id);


--
-- Name: idx_16577393_uniq.sales_channel_domain.url; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577393_uniq.sales_channel_domain.url" ON public.sales_channel_domain USING btree (url);


--
-- Name: idx_16577408_uniq.sales_channel_file.sc_id_family_file_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577408_uniq.sales_channel_file.sc_id_family_file_name" ON public.sales_channel_file USING btree (sales_channel_id, file_family, file_name);


--
-- Name: idx_16577420_fk.sales_channel_language.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577420_fk.sales_channel_language.language_id" ON public.sales_channel_language USING btree (language_id);


--
-- Name: idx_16577427_fk.sales_channel_payment_method.payment_method_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577427_fk.sales_channel_payment_method.payment_method_id" ON public.sales_channel_payment_method USING btree (payment_method_id);


--
-- Name: idx_16577434_fk.sales_channel_shipping_method.shipping_method_i; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577434_fk.sales_channel_shipping_method.shipping_method_i" ON public.sales_channel_shipping_method USING btree (shipping_method_id);


--
-- Name: idx_16577441_idx.sales_channel_tracking_customer.sales_channel_; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577441_idx.sales_channel_tracking_customer.sales_channel_" ON public.sales_channel_tracking_customer USING btree (sales_channel_id);


--
-- Name: idx_16577441_uniq.sales_channel_tracking_customer.customer; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577441_uniq.sales_channel_tracking_customer.customer" ON public.sales_channel_tracking_customer USING btree (customer_id);


--
-- Name: idx_16577450_idx.sales_channel_tracking_order.sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577450_idx.sales_channel_tracking_order.sales_channel_id" ON public.sales_channel_tracking_order USING btree (sales_channel_id);


--
-- Name: idx_16577450_uniq.sales_channel_tracking_order.order; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577450_uniq.sales_channel_tracking_order.order" ON public.sales_channel_tracking_order USING btree (order_id, order_version_id);


--
-- Name: idx_16577460_fk.sales_channel_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577460_fk.sales_channel_translation.language_id" ON public.sales_channel_translation USING btree (language_id);


--
-- Name: idx_16577477_fk.sales_channel_type_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577477_fk.sales_channel_type_translation.language_id" ON public.sales_channel_type_translation USING btree (language_id);


--
-- Name: idx_16577485_uniq.salutation_key; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577485_uniq.salutation_key" ON public.salutation USING btree (salutation_key);


--
-- Name: idx_16577495_fk.salutation_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577495_fk.salutation_translation.language_id" ON public.salutation_translation USING btree (language_id);


--
-- Name: idx_16577503_uniq.scheduled_task.scheduled_task_class; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577503_uniq.scheduled_task.scheduled_task_class" ON public.scheduled_task USING btree (scheduled_task_class);


--
-- Name: idx_16577516_fk.app_script.app_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577516_fk.app_script.app_id" ON public.script USING btree (app_id);


--
-- Name: idx_16577516_idx.app_script.hook; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577516_idx.app_script.hook" ON public.script USING btree (hook);


--
-- Name: idx_16577527_fk.seo_url.sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577527_fk.seo_url.sales_channel_id" ON public.seo_url USING btree (sales_channel_id);


--
-- Name: idx_16577527_idx.delete_query; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577527_idx.delete_query" ON public.seo_url USING btree (foreign_key, sales_channel_id);


--
-- Name: idx_16577527_idx.foreign_key; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577527_idx.foreign_key" ON public.seo_url USING btree (language_id, foreign_key, sales_channel_id, is_canonical);


--
-- Name: idx_16577527_idx.path_info; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577527_idx.path_info" ON public.seo_url USING btree (language_id, sales_channel_id, is_canonical, path_info);


--
-- Name: idx_16577527_idx.seo_url.language_path; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577527_idx.seo_url.language_path" ON public.seo_url USING btree (language_id, seo_path_info);


--
-- Name: idx_16577527_uniq.seo_url.foreign_key; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577527_uniq.seo_url.foreign_key" ON public.seo_url USING btree (language_id, sales_channel_id, foreign_key, route_name, is_canonical);


--
-- Name: idx_16577527_uniq.seo_url.seo_path_info; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577527_uniq.seo_url.seo_path_info" ON public.seo_url USING btree (language_id, sales_channel_id, seo_path_info);


--
-- Name: idx_16577543_uniq.seo_url_template.route_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577543_uniq.seo_url_template.route_name" ON public.seo_url_template USING btree (sales_channel_id, route_name);


--
-- Name: idx_16577556_fk.shipping_method.availability_rule_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577556_fk.shipping_method.availability_rule_id" ON public.shipping_method USING btree (availability_rule_id);


--
-- Name: idx_16577556_fk.shipping_method.delivery_time_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577556_fk.shipping_method.delivery_time_id" ON public.shipping_method USING btree (delivery_time_id);


--
-- Name: idx_16577556_fk.shipping_method.media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577556_fk.shipping_method.media_id" ON public.shipping_method USING btree (media_id);


--
-- Name: idx_16577556_fk.shipping_method.tax_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577556_fk.shipping_method.tax_id" ON public.shipping_method USING btree (tax_id);


--
-- Name: idx_16577556_uniq.technical_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577556_uniq.technical_name" ON public.shipping_method USING btree (technical_name);


--
-- Name: idx_16577570_fk.shipping_method_price.calculation_rule_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577570_fk.shipping_method_price.calculation_rule_id" ON public.shipping_method_price USING btree (calculation_rule_id);


--
-- Name: idx_16577570_fk.shipping_method_price.rule_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577570_fk.shipping_method_price.rule_id" ON public.shipping_method_price USING btree (rule_id);


--
-- Name: idx_16577570_uniq.shipping_method_quantity_start; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577570_uniq.shipping_method_quantity_start" ON public.shipping_method_price USING btree (shipping_method_id, rule_id, quantity_start);


--
-- Name: idx_16577578_fk.shipping_method_tag.tag_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577578_fk.shipping_method_tag.tag_id" ON public.shipping_method_tag USING btree (tag_id);


--
-- Name: idx_16577585_fk.shipping_method_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577585_fk.shipping_method_translation.language_id" ON public.shipping_method_translation USING btree (language_id);


--
-- Name: idx_16577593_uniq.snippet_set_id_translation_key; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577593_uniq.snippet_set_id_translation_key" ON public.snippet USING btree (snippet_set_id, translation_key);


--
-- Name: idx_16577614_fk.state_machine.initial_state_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577614_fk.state_machine.initial_state_id" ON public.state_machine USING btree (initial_state_id);


--
-- Name: idx_16577614_uniq.state_machine.technical_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577614_uniq.state_machine.technical_name" ON public.state_machine USING btree (technical_name);


--
-- Name: idx_16577622_fk.state_machine_history.from_state_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577622_fk.state_machine_history.from_state_id" ON public.state_machine_history USING btree (from_state_id);


--
-- Name: idx_16577622_fk.state_machine_history.integration_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577622_fk.state_machine_history.integration_id" ON public.state_machine_history USING btree (integration_id);


--
-- Name: idx_16577622_fk.state_machine_history.state_machine_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577622_fk.state_machine_history.state_machine_id" ON public.state_machine_history USING btree (state_machine_id);


--
-- Name: idx_16577622_fk.state_machine_history.to_state_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577622_fk.state_machine_history.to_state_id" ON public.state_machine_history USING btree (to_state_id);


--
-- Name: idx_16577622_fk.state_machine_history.user_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577622_fk.state_machine_history.user_id" ON public.state_machine_history USING btree (user_id);


--
-- Name: idx_16577622_idx.state_machine_history.referenced_entity; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577622_idx.state_machine_history.referenced_entity" ON public.state_machine_history USING btree (referenced_id, referenced_version_id);


--
-- Name: idx_16577636_idx.state_machine_state.state_machine_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577636_idx.state_machine_state.state_machine_id" ON public.state_machine_state USING btree (state_machine_id);


--
-- Name: idx_16577636_uniq.technical_name_state_machine; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577636_uniq.technical_name_state_machine" ON public.state_machine_state USING btree (technical_name, state_machine_id);


--
-- Name: idx_16577645_idx.language; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577645_idx.language" ON public.state_machine_state_translation USING btree (language_id);


--
-- Name: idx_16577645_idx.state_machine; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577645_idx.state_machine" ON public.state_machine_state_translation USING btree (state_machine_state_id);


--
-- Name: idx_16577653_idx.state_machine_transition.from_state_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577653_idx.state_machine_transition.from_state_id" ON public.state_machine_transition USING btree (from_state_id);


--
-- Name: idx_16577653_idx.state_machine_transition.state_machine_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577653_idx.state_machine_transition.state_machine_id" ON public.state_machine_transition USING btree (state_machine_id);


--
-- Name: idx_16577653_idx.state_machine_transition.to_state_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577653_idx.state_machine_transition.to_state_id" ON public.state_machine_transition USING btree (to_state_id);


--
-- Name: idx_16577653_uniq.state_machine_transition.action_name_state_ma; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577653_uniq.state_machine_transition.action_name_state_ma" ON public.state_machine_transition USING btree (action_name, state_machine_id, from_state_id, to_state_id);


--
-- Name: idx_16577664_idx.state_machine_translation.language; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577664_idx.state_machine_translation.language" ON public.state_machine_translation USING btree (language_id);


--
-- Name: idx_16577664_idx.state_machine_translation.state_machine; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577664_idx.state_machine_translation.state_machine" ON public.state_machine_translation USING btree (state_machine_id);


--
-- Name: idx_16577672_fk.system_config.sales_channel_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577672_fk.system_config.sales_channel_id" ON public.system_config USING btree (sales_channel_id);


--
-- Name: idx_16577672_uniq.system_config.configuration_key__sales_channe; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577672_uniq.system_config.configuration_key__sales_channe" ON public.system_config USING btree (configuration_key, sales_channel_id);


--
-- Name: idx_16577689_idx.tax.tax; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577689_idx.tax.tax" ON public.tax USING btree (tax_rate);


--
-- Name: idx_16577699_availability_rule_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_16577699_availability_rule_id ON public.tax_provider USING btree (availability_rule_id, active);


--
-- Name: idx_16577699_fk.tax_provider.app_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577699_fk.tax_provider.app_id" ON public.tax_provider USING btree (app_id);


--
-- Name: idx_16577699_uniq.tax_provider.identifier; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577699_uniq.tax_provider.identifier" ON public.tax_provider USING btree (identifier);


--
-- Name: idx_16577711_fk.tax_provider_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577711_fk.tax_provider_translation.language_id" ON public.tax_provider_translation USING btree (language_id);


--
-- Name: idx_16577719_fk.tax_rule.country_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577719_fk.tax_rule.country_id" ON public.tax_rule USING btree (country_id);


--
-- Name: idx_16577719_fk.tax_rule.tax_area_rule_type_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577719_fk.tax_rule.tax_area_rule_type_id" ON public.tax_rule USING btree (tax_rule_type_id);


--
-- Name: idx_16577719_fk.tax_rule.tax_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577719_fk.tax_rule.tax_id" ON public.tax_rule USING btree (tax_id);


--
-- Name: idx_16577729_uniq.technical_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577729_uniq.technical_name" ON public.tax_rule_type USING btree (technical_name);


--
-- Name: idx_16577738_fk.tax_rule_type_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577738_fk.tax_rule_type_translation.language_id" ON public.tax_rule_type_translation USING btree (language_id);


--
-- Name: idx_16577746_fk.theme.preview_media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577746_fk.theme.preview_media_id" ON public.theme USING btree (preview_media_id);


--
-- Name: idx_16577746_uniq.theme.technical_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577746_uniq.theme.technical_name" ON public.theme USING btree (technical_name);


--
-- Name: idx_16577756_fk.theme_child.child_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577756_fk.theme_child.child_id" ON public.theme_child USING btree (child_id);


--
-- Name: idx_16577763_fk.theme_media.media_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577763_fk.theme_media.media_id" ON public.theme_media USING btree (media_id);


--
-- Name: idx_16577770_uidx.technical_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577770_uidx.technical_name" ON public.theme_runtime_config USING btree (technical_name);


--
-- Name: idx_16577780_fk.theme_sales_channel.theme_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577780_fk.theme_sales_channel.theme_id" ON public.theme_sales_channel USING btree (theme_id);


--
-- Name: idx_16577787_fk.theme_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577787_fk.theme_translation.language_id" ON public.theme_translation USING btree (language_id);


--
-- Name: idx_16577802_fk.unit_translation.language_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577802_fk.unit_translation.language_id" ON public.unit_translation USING btree (language_id);


--
-- Name: idx_16577819_fk.user.avatar_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577819_fk.user.avatar_id" ON public."user" USING btree (avatar_id);


--
-- Name: idx_16577819_fk.user.locale_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577819_fk.user.locale_id" ON public."user" USING btree (locale_id);


--
-- Name: idx_16577819_uniq.user.email; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577819_uniq.user.email" ON public."user" USING btree (email);


--
-- Name: idx_16577819_uniq.user.username; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577819_uniq.user.username" ON public."user" USING btree (username);


--
-- Name: idx_16577836_idx.user_access_key.access_key; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577836_idx.user_access_key.access_key" ON public.user_access_key USING btree (access_key);


--
-- Name: idx_16577836_idx.user_access_key.user_id_; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577836_idx.user_access_key.user_id_" ON public.user_access_key USING btree (user_id);


--
-- Name: idx_16577846_uniq.user_id_key; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577846_uniq.user_id_key" ON public.user_config USING btree (user_id, key);


--
-- Name: idx_16577855_uniq.user_recovery.user_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577855_uniq.user_recovery.user_id" ON public.user_recovery USING btree (user_id);


--
-- Name: idx_16577864_idx.version.created_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577864_idx.version.created_at" ON public.version USING btree (created_at);


--
-- Name: idx_16577873_auto_increment; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_16577873_auto_increment ON public.version_commit USING btree (auto_increment);


--
-- Name: idx_16577873_idx.version_commit.created_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577873_idx.version_commit.created_at" ON public.version_commit USING btree (created_at);


--
-- Name: idx_16577886_auto_increment; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_16577886_auto_increment ON public.version_commit_data USING btree (auto_increment);


--
-- Name: idx_16577886_fk.version_commit_data.version_commit_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577886_fk.version_commit_data.version_commit_id" ON public.version_commit_data USING btree (version_commit_id);


--
-- Name: idx_16577900_fk.webhook.app_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577900_fk.webhook.app_id" ON public.webhook USING btree (app_id);


--
-- Name: idx_16577900_uniq.webhook.name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577900_uniq.webhook.name" ON public.webhook USING btree (name, app_id);


--
-- Name: idx_16577916_idx.webhook_delivery.partition_status_retry; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577916_idx.webhook_delivery.partition_status_retry" ON public.webhook_delivery USING btree (partition_key, delivery_status, next_retry_at, id);


--
-- Name: idx_16577916_idx.webhook_delivery.webhook_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577916_idx.webhook_delivery.webhook_status" ON public.webhook_delivery USING btree (webhook_id, delivery_status);


--
-- Name: idx_16577916_uniq.webhook_delivery.webhook_event_log_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577916_uniq.webhook_delivery.webhook_event_log_id" ON public.webhook_delivery USING btree (webhook_event_log_id);


--
-- Name: idx_16577943_idx.webhook_stream.claim; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_16577943_idx.webhook_stream.claim" ON public.webhook_stream USING btree (last_claimed_at);


--
-- Name: idx_16577943_uniq.webhook_stream.partition_key; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX "idx_16577943_uniq.webhook_stream.partition_key" ON public.webhook_stream USING btree (partition_key);


--
-- Name: sales_channel_api_context on_update_current_timestamp; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER on_update_current_timestamp BEFORE UPDATE ON public.sales_channel_api_context FOR EACH ROW EXECUTE FUNCTION public.on_update_current_timestamp_sales_channel_api_context();


--
-- Name: currency_country_rounding currency_country_rounding_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_country_rounding
    ADD CONSTRAINT currency_country_rounding_ibfk_2 FOREIGN KEY (currency_id) REFERENCES public.currency(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: currency_country_rounding currency_country_rounding_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_country_rounding
    ADD CONSTRAINT currency_country_rounding_ibfk_3 FOREIGN KEY (country_id) REFERENCES public.country(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: acl_user_role fk.acl_user_role.acl_role_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.acl_user_role
    ADD CONSTRAINT "fk.acl_user_role.acl_role_id" FOREIGN KEY (acl_role_id) REFERENCES public.acl_role(id) ON DELETE CASCADE;


--
-- Name: acl_user_role fk.acl_user_role.user_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.acl_user_role
    ADD CONSTRAINT "fk.acl_user_role.user_id" FOREIGN KEY (user_id) REFERENCES public."user"(id) ON DELETE CASCADE;


--
-- Name: app fk.app.acl_role_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app
    ADD CONSTRAINT "fk.app.acl_role_id" FOREIGN KEY (acl_role_id) REFERENCES public.acl_role(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: app fk.app.integration_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app
    ADD CONSTRAINT "fk.app.integration_id" FOREIGN KEY (integration_id) REFERENCES public.integration(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: app_action_button fk.app_action_button.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_action_button
    ADD CONSTRAINT "fk.app_action_button.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_action_button_translation fk.app_action_button_translation.app_action_button_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_action_button_translation
    ADD CONSTRAINT "fk.app_action_button_translation.app_action_button_id" FOREIGN KEY (app_action_button_id) REFERENCES public.app_action_button(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_action_button_translation fk.app_action_button_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_action_button_translation
    ADD CONSTRAINT "fk.app_action_button_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_cms_block_translation fk.app_cms_block.app_cms_block_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_cms_block_translation
    ADD CONSTRAINT "fk.app_cms_block.app_cms_block_id" FOREIGN KEY (app_cms_block_id) REFERENCES public.app_cms_block(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_cms_block fk.app_cms_block.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_cms_block
    ADD CONSTRAINT "fk.app_cms_block.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_cms_block_translation fk.app_cms_block.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_cms_block_translation
    ADD CONSTRAINT "fk.app_cms_block.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_feature fk.app_feature.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_feature
    ADD CONSTRAINT "fk.app_feature.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: app_flow_action fk.app_flow_action.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_flow_action
    ADD CONSTRAINT "fk.app_flow_action.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_flow_action_translation fk.app_flow_action_translation.app_flow_action_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_flow_action_translation
    ADD CONSTRAINT "fk.app_flow_action_translation.app_flow_action_id" FOREIGN KEY (app_flow_action_id) REFERENCES public.app_flow_action(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_flow_action_translation fk.app_flow_action_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_flow_action_translation
    ADD CONSTRAINT "fk.app_flow_action_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_flow_event fk.app_flow_event.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_flow_event
    ADD CONSTRAINT "fk.app_flow_event.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_administration_snippet fk.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_administration_snippet
    ADD CONSTRAINT "fk.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_mcp_prompt fk.app_mcp_prompt.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_mcp_prompt
    ADD CONSTRAINT "fk.app_mcp_prompt.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_mcp_prompt_translation fk.app_mcp_prompt_translation.app_mcp_prompt_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_mcp_prompt_translation
    ADD CONSTRAINT "fk.app_mcp_prompt_translation.app_mcp_prompt_id" FOREIGN KEY (app_mcp_prompt_id) REFERENCES public.app_mcp_prompt(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_mcp_prompt_translation fk.app_mcp_prompt_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_mcp_prompt_translation
    ADD CONSTRAINT "fk.app_mcp_prompt_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_mcp_resource fk.app_mcp_resource.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_mcp_resource
    ADD CONSTRAINT "fk.app_mcp_resource.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_mcp_resource_translation fk.app_mcp_resource_translation.app_mcp_resource_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_mcp_resource_translation
    ADD CONSTRAINT "fk.app_mcp_resource_translation.app_mcp_resource_id" FOREIGN KEY (app_mcp_resource_id) REFERENCES public.app_mcp_resource(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_mcp_resource_translation fk.app_mcp_resource_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_mcp_resource_translation
    ADD CONSTRAINT "fk.app_mcp_resource_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_mcp_tool fk.app_mcp_tool.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_mcp_tool
    ADD CONSTRAINT "fk.app_mcp_tool.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_mcp_tool_translation fk.app_mcp_tool_translation.app_mcp_tool_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_mcp_tool_translation
    ADD CONSTRAINT "fk.app_mcp_tool_translation.app_mcp_tool_id" FOREIGN KEY (app_mcp_tool_id) REFERENCES public.app_mcp_tool(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_mcp_tool_translation fk.app_mcp_tool_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_mcp_tool_translation
    ADD CONSTRAINT "fk.app_mcp_tool_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_payment_method fk.app_payment_method.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_payment_method
    ADD CONSTRAINT "fk.app_payment_method.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: app_payment_method fk.app_payment_method.original_media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_payment_method
    ADD CONSTRAINT "fk.app_payment_method.original_media_id" FOREIGN KEY (original_media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: app_payment_method fk.app_payment_method.payment_method_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_payment_method
    ADD CONSTRAINT "fk.app_payment_method.payment_method_id" FOREIGN KEY (payment_method_id) REFERENCES public.payment_method(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: script fk.app_script.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.script
    ADD CONSTRAINT "fk.app_script.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_script_condition fk.app_script_condition.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_script_condition
    ADD CONSTRAINT "fk.app_script_condition.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_script_condition_translation fk.app_script_condition_translation.app_script_condition_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_script_condition_translation
    ADD CONSTRAINT "fk.app_script_condition_translation.app_script_condition_id" FOREIGN KEY (app_script_condition_id) REFERENCES public.app_script_condition(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_script_condition_translation fk.app_script_condition_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_script_condition_translation
    ADD CONSTRAINT "fk.app_script_condition_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_shipping_method fk.app_shipping_method.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_shipping_method
    ADD CONSTRAINT "fk.app_shipping_method.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: app_shipping_method fk.app_shipping_method.original_media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_shipping_method
    ADD CONSTRAINT "fk.app_shipping_method.original_media_id" FOREIGN KEY (original_media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: app_shipping_method fk.app_shipping_method.shipping_method_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_shipping_method
    ADD CONSTRAINT "fk.app_shipping_method.shipping_method_id" FOREIGN KEY (shipping_method_id) REFERENCES public.shipping_method(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_translation fk.app_translation.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_translation
    ADD CONSTRAINT "fk.app_translation.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_translation fk.app_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_translation
    ADD CONSTRAINT "fk.app_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: category fk.category.after_category_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category
    ADD CONSTRAINT "fk.category.after_category_id" FOREIGN KEY (after_category_id, after_category_version_id) REFERENCES public.category(id, version_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: category fk.category.cms_page_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category
    ADD CONSTRAINT "fk.category.cms_page_id" FOREIGN KEY (cms_page_id, cms_page_version_id) REFERENCES public.cms_page(id, version_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: category fk.category.custom_entity_type_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category
    ADD CONSTRAINT "fk.category.custom_entity_type_id" FOREIGN KEY (custom_entity_type_id) REFERENCES public.custom_entity(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: category fk.category.media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category
    ADD CONSTRAINT "fk.category.media_id" FOREIGN KEY (media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: category fk.category.parent_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category
    ADD CONSTRAINT "fk.category.parent_id" FOREIGN KEY (parent_id, parent_version_id) REFERENCES public.category(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: category fk.category.product_stream_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category
    ADD CONSTRAINT "fk.category.product_stream_id" FOREIGN KEY (product_stream_id) REFERENCES public.product_stream(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: category_tag fk.category_tag.category_tag__category_version_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category_tag
    ADD CONSTRAINT "fk.category_tag.category_tag__category_version_id" FOREIGN KEY (category_id, category_version_id) REFERENCES public.category(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: category_tag fk.category_tag.tag_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category_tag
    ADD CONSTRAINT "fk.category_tag.tag_id" FOREIGN KEY (tag_id) REFERENCES public.tag(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: category_translation fk.category_translation.category_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category_translation
    ADD CONSTRAINT "fk.category_translation.category_id" FOREIGN KEY (category_id, category_version_id) REFERENCES public.category(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: category_translation fk.category_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category_translation
    ADD CONSTRAINT "fk.category_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: cms_block fk.cms_block.background_media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cms_block
    ADD CONSTRAINT "fk.cms_block.background_media_id" FOREIGN KEY (background_media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: cms_block fk.cms_block.cms_section_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cms_block
    ADD CONSTRAINT "fk.cms_block.cms_section_id" FOREIGN KEY (cms_section_id, cms_section_version_id) REFERENCES public.cms_section(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: cms_page fk.cms_page.preview_media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cms_page
    ADD CONSTRAINT "fk.cms_page.preview_media_id" FOREIGN KEY (preview_media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: cms_page_translation fk.cms_page_translation.cms_page_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cms_page_translation
    ADD CONSTRAINT "fk.cms_page_translation.cms_page_id" FOREIGN KEY (cms_page_id, cms_page_version_id) REFERENCES public.cms_page(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: cms_page_translation fk.cms_page_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cms_page_translation
    ADD CONSTRAINT "fk.cms_page_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: cms_section fk.cms_section.background_media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cms_section
    ADD CONSTRAINT "fk.cms_section.background_media_id" FOREIGN KEY (background_media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: cms_section fk.cms_section.cms_page_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cms_section
    ADD CONSTRAINT "fk.cms_section.cms_page_id" FOREIGN KEY (cms_page_id, cms_page_version_id) REFERENCES public.cms_page(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: cms_slot fk.cms_slot.cms_block_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cms_slot
    ADD CONSTRAINT "fk.cms_slot.cms_block_id" FOREIGN KEY (cms_block_id, cms_block_version_id) REFERENCES public.cms_block(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: cms_slot_translation fk.cms_slot_translation.cms_slot_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cms_slot_translation
    ADD CONSTRAINT "fk.cms_slot_translation.cms_slot_id" FOREIGN KEY (cms_slot_id, cms_slot_version_id) REFERENCES public.cms_slot(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: cms_slot_translation fk.cms_slot_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cms_slot_translation
    ADD CONSTRAINT "fk.cms_slot_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: country_state fk.country_state.country_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.country_state
    ADD CONSTRAINT "fk.country_state.country_id" FOREIGN KEY (country_id) REFERENCES public.country(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: country_state_translation fk.country_state_translation.country_state_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.country_state_translation
    ADD CONSTRAINT "fk.country_state_translation.country_state_id" FOREIGN KEY (country_state_id) REFERENCES public.country_state(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: country_state_translation fk.country_state_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.country_state_translation
    ADD CONSTRAINT "fk.country_state_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: country_translation fk.country_translation.country_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.country_translation
    ADD CONSTRAINT "fk.country_translation.country_id" FOREIGN KEY (country_id) REFERENCES public.country(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: country_translation fk.country_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.country_translation
    ADD CONSTRAINT "fk.country_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: currency_translation fk.currency_translation.currency_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_translation
    ADD CONSTRAINT "fk.currency_translation.currency_id" FOREIGN KEY (currency_id) REFERENCES public.currency(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: currency_translation fk.currency_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_translation
    ADD CONSTRAINT "fk.currency_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: custom_entity fk.custom_entity.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.custom_entity
    ADD CONSTRAINT "fk.custom_entity.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: custom_entity fk.custom_entity.plugin_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.custom_entity
    ADD CONSTRAINT "fk.custom_entity.plugin_id" FOREIGN KEY (plugin_id) REFERENCES public.plugin(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: custom_field fk.custom_field.set_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.custom_field
    ADD CONSTRAINT "fk.custom_field.set_id" FOREIGN KEY (set_id) REFERENCES public.custom_field_set(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: custom_field_set fk.custom_field_set.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.custom_field_set
    ADD CONSTRAINT "fk.custom_field_set.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: custom_field_set_relation fk.custom_field_set_relation.set_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.custom_field_set_relation
    ADD CONSTRAINT "fk.custom_field_set_relation.set_id" FOREIGN KEY (set_id) REFERENCES public.custom_field_set(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: customer fk.customer.bound_sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer
    ADD CONSTRAINT "fk.customer.bound_sales_channel_id" FOREIGN KEY (bound_sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: customer fk.customer.created_by_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer
    ADD CONSTRAINT "fk.customer.created_by_id" FOREIGN KEY (created_by_id) REFERENCES public."user"(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: customer fk.customer.customer_group_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer
    ADD CONSTRAINT "fk.customer.customer_group_id" FOREIGN KEY (customer_group_id) REFERENCES public.customer_group(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: customer fk.customer.default_payment_method_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer
    ADD CONSTRAINT "fk.customer.default_payment_method_id" FOREIGN KEY (default_payment_method_id) REFERENCES public.payment_method(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: customer fk.customer.last_payment_method_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer
    ADD CONSTRAINT "fk.customer.last_payment_method_id" FOREIGN KEY (last_payment_method_id) REFERENCES public.payment_method(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: customer fk.customer.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer
    ADD CONSTRAINT "fk.customer.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: customer fk.customer.salutation_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer
    ADD CONSTRAINT "fk.customer.salutation_id" FOREIGN KEY (salutation_id) REFERENCES public.salutation(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: customer fk.customer.updated_by_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer
    ADD CONSTRAINT "fk.customer.updated_by_id" FOREIGN KEY (updated_by_id) REFERENCES public."user"(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: customer_address fk.customer_address.country_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_address
    ADD CONSTRAINT "fk.customer_address.country_id" FOREIGN KEY (country_id) REFERENCES public.country(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: customer_address fk.customer_address.country_state_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_address
    ADD CONSTRAINT "fk.customer_address.country_state_id" FOREIGN KEY (country_state_id) REFERENCES public.country_state(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: customer_address fk.customer_address.customer_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_address
    ADD CONSTRAINT "fk.customer_address.customer_id" FOREIGN KEY (customer_id) REFERENCES public.customer(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: customer_address fk.customer_address.salutation_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_address
    ADD CONSTRAINT "fk.customer_address.salutation_id" FOREIGN KEY (salutation_id) REFERENCES public.salutation(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: customer_group_registration_sales_channels fk.customer_group_registration_sales_channels.customer_group_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_group_registration_sales_channels
    ADD CONSTRAINT "fk.customer_group_registration_sales_channels.customer_group_id" FOREIGN KEY (customer_group_id) REFERENCES public.customer_group(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: customer_group_registration_sales_channels fk.customer_group_registration_sales_channels.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_group_registration_sales_channels
    ADD CONSTRAINT "fk.customer_group_registration_sales_channels.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: customer_group_translation fk.customer_group_translation.customer_group_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_group_translation
    ADD CONSTRAINT "fk.customer_group_translation.customer_group_id" FOREIGN KEY (customer_group_id) REFERENCES public.customer_group(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: customer_group_translation fk.customer_group_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_group_translation
    ADD CONSTRAINT "fk.customer_group_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: customer_recovery fk.customer_recovery.customer_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_recovery
    ADD CONSTRAINT "fk.customer_recovery.customer_id" FOREIGN KEY (customer_id) REFERENCES public.customer(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: customer_tag fk.customer_tag.customer_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_tag
    ADD CONSTRAINT "fk.customer_tag.customer_id" FOREIGN KEY (customer_id) REFERENCES public.customer(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: customer_tag fk.customer_tag.tag_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_tag
    ADD CONSTRAINT "fk.customer_tag.tag_id" FOREIGN KEY (tag_id) REFERENCES public.tag(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: customer_wishlist fk.customer_wishlist.customer_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_wishlist
    ADD CONSTRAINT "fk.customer_wishlist.customer_id" FOREIGN KEY (customer_id) REFERENCES public.customer(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: customer_wishlist fk.customer_wishlist.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_wishlist
    ADD CONSTRAINT "fk.customer_wishlist.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: customer_wishlist_product fk.customer_wishlist_product.customer_wishlist_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_wishlist_product
    ADD CONSTRAINT "fk.customer_wishlist_product.customer_wishlist_id" FOREIGN KEY (customer_wishlist_id) REFERENCES public.customer_wishlist(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: customer_wishlist_product fk.customer_wishlist_product.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_wishlist_product
    ADD CONSTRAINT "fk.customer_wishlist_product.product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: delivery_time_translation fk.delivery_time_translation.delivery_time_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.delivery_time_translation
    ADD CONSTRAINT "fk.delivery_time_translation.delivery_time_id" FOREIGN KEY (delivery_time_id) REFERENCES public.delivery_time(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: delivery_time_translation fk.delivery_time_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.delivery_time_translation
    ADD CONSTRAINT "fk.delivery_time_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document fk.document.document_a11y_media_file_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document
    ADD CONSTRAINT "fk.document.document_a11y_media_file_id" FOREIGN KEY (document_a11y_media_file_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: document fk.document.document_media_file_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document
    ADD CONSTRAINT "fk.document.document_media_file_id" FOREIGN KEY (document_media_file_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: document fk.document.document_type_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document
    ADD CONSTRAINT "fk.document.document_type_id" FOREIGN KEY (document_type_id) REFERENCES public.document_type(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: document fk.document.order_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document
    ADD CONSTRAINT "fk.document.order_id" FOREIGN KEY (order_id, order_version_id) REFERENCES public."order"(id, version_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: document fk.document.referenced_document_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document
    ADD CONSTRAINT "fk.document.referenced_document_id" FOREIGN KEY (referenced_document_id) REFERENCES public.document(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: document_base_config fk.document_base_config.logo_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_base_config
    ADD CONSTRAINT "fk.document_base_config.logo_id" FOREIGN KEY (logo_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: document_base_config fk.document_base_config.type_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_base_config
    ADD CONSTRAINT "fk.document_base_config.type_id" FOREIGN KEY (document_type_id) REFERENCES public.document_type(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_base_config_sales_channel fk.document_base_config_sales_channel.document_base_config_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_base_config_sales_channel
    ADD CONSTRAINT "fk.document_base_config_sales_channel.document_base_config_id" FOREIGN KEY (document_base_config_id) REFERENCES public.document_base_config(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_base_config_sales_channel fk.document_base_config_sales_channel.document_type_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_base_config_sales_channel
    ADD CONSTRAINT "fk.document_base_config_sales_channel.document_type_id" FOREIGN KEY (document_type_id) REFERENCES public.document_type(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_base_config_sales_channel fk.document_base_config_sales_channel.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_base_config_sales_channel
    ADD CONSTRAINT "fk.document_base_config_sales_channel.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_file fk.document_file.document_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_file
    ADD CONSTRAINT "fk.document_file.document_id" FOREIGN KEY (document_id) REFERENCES public.document(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_file fk.document_file.media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_file
    ADD CONSTRAINT "fk.document_file.media_id" FOREIGN KEY (media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: document_type_translation fk.document_type_translation.document_type_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_type_translation
    ADD CONSTRAINT "fk.document_type_translation.document_type_id" FOREIGN KEY (document_type_id) REFERENCES public.document_type(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: document_type_translation fk.document_type_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_type_translation
    ADD CONSTRAINT "fk.document_type_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: flow fk.flow.app_flow_event_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flow
    ADD CONSTRAINT "fk.flow.app_flow_event_id" FOREIGN KEY (app_flow_event_id) REFERENCES public.app_flow_event(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: flow_sequence fk.flow_sequence.app_flow_action_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flow_sequence
    ADD CONSTRAINT "fk.flow_sequence.app_flow_action_id" FOREIGN KEY (app_flow_action_id) REFERENCES public.app_flow_action(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: flow_sequence fk.flow_sequence.flow_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flow_sequence
    ADD CONSTRAINT "fk.flow_sequence.flow_id" FOREIGN KEY (flow_id) REFERENCES public.flow(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: flow_sequence fk.flow_sequence.parent_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flow_sequence
    ADD CONSTRAINT "fk.flow_sequence.parent_id" FOREIGN KEY (parent_id) REFERENCES public.flow_sequence(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: flow_sequence fk.flow_sequence.rule_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flow_sequence
    ADD CONSTRAINT "fk.flow_sequence.rule_id" FOREIGN KEY (rule_id) REFERENCES public.rule(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: import_export_log fk.import_export_log.file_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_export_log
    ADD CONSTRAINT "fk.import_export_log.file_id" FOREIGN KEY (file_id) REFERENCES public.import_export_file(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: import_export_log fk.import_export_log.invalid_records_log_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_export_log
    ADD CONSTRAINT "fk.import_export_log.invalid_records_log_id" FOREIGN KEY (invalid_records_log_id) REFERENCES public.import_export_log(id) ON DELETE SET NULL;


--
-- Name: import_export_log fk.import_export_log.profile_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_export_log
    ADD CONSTRAINT "fk.import_export_log.profile_id" FOREIGN KEY (profile_id) REFERENCES public.import_export_profile(id) ON DELETE SET NULL;


--
-- Name: import_export_log fk.import_export_log.user_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_export_log
    ADD CONSTRAINT "fk.import_export_log.user_id" FOREIGN KEY (user_id) REFERENCES public."user"(id) ON DELETE SET NULL;


--
-- Name: import_export_profile_translation fk.import_export_profile_translation.import_export_profile_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_export_profile_translation
    ADD CONSTRAINT "fk.import_export_profile_translation.import_export_profile_id" FOREIGN KEY (import_export_profile_id) REFERENCES public.import_export_profile(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: import_export_profile_translation fk.import_export_profile_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_export_profile_translation
    ADD CONSTRAINT "fk.import_export_profile_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: integration_role fk.integration_acl_role.acl_role_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.integration_role
    ADD CONSTRAINT "fk.integration_acl_role.acl_role_id" FOREIGN KEY (acl_role_id) REFERENCES public.acl_role(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: integration_role fk.integration_acl_role.integration_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.integration_role
    ADD CONSTRAINT "fk.integration_acl_role.integration_id" FOREIGN KEY (integration_id) REFERENCES public.integration(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: landing_page fk.landing_page.cms_page_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.landing_page
    ADD CONSTRAINT "fk.landing_page.cms_page_id" FOREIGN KEY (cms_page_id, cms_page_version_id) REFERENCES public.cms_page(id, version_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: landing_page_sales_channel fk.landing_page_sales_channel.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.landing_page_sales_channel
    ADD CONSTRAINT "fk.landing_page_sales_channel.product_id" FOREIGN KEY (landing_page_id, landing_page_version_id) REFERENCES public.landing_page(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: landing_page_sales_channel fk.landing_page_sales_channel.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.landing_page_sales_channel
    ADD CONSTRAINT "fk.landing_page_sales_channel.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: landing_page_tag fk.landing_page_tag.landing_page_version_id__landing_page_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.landing_page_tag
    ADD CONSTRAINT "fk.landing_page_tag.landing_page_version_id__landing_page_id" FOREIGN KEY (landing_page_id, landing_page_version_id) REFERENCES public.landing_page(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: landing_page_tag fk.landing_page_tag.tag_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.landing_page_tag
    ADD CONSTRAINT "fk.landing_page_tag.tag_id" FOREIGN KEY (tag_id) REFERENCES public.tag(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: landing_page_translation fk.landing_page_translation.landing_page_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.landing_page_translation
    ADD CONSTRAINT "fk.landing_page_translation.landing_page_id" FOREIGN KEY (landing_page_id, landing_page_version_id) REFERENCES public.landing_page(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: landing_page_translation fk.landing_page_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.landing_page_translation
    ADD CONSTRAINT "fk.landing_page_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: language fk.language.locale_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.language
    ADD CONSTRAINT "fk.language.locale_id" FOREIGN KEY (locale_id) REFERENCES public.locale(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: language fk.language.parent_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.language
    ADD CONSTRAINT "fk.language.parent_id" FOREIGN KEY (parent_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: language fk.language.translation_code_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.language
    ADD CONSTRAINT "fk.language.translation_code_id" FOREIGN KEY (translation_code_id) REFERENCES public.locale(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order fk.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public."order"
    ADD CONSTRAINT "fk.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: app_administration_snippet fk.locale_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_administration_snippet
    ADD CONSTRAINT "fk.locale_id" FOREIGN KEY (locale_id) REFERENCES public.locale(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: locale_translation fk.locale_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locale_translation
    ADD CONSTRAINT "fk.locale_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: locale_translation fk.locale_translation.locale_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locale_translation
    ADD CONSTRAINT "fk.locale_translation.locale_id" FOREIGN KEY (locale_id) REFERENCES public.locale(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: mail_header_footer_translation fk.mail_header_footer_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mail_header_footer_translation
    ADD CONSTRAINT "fk.mail_header_footer_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: mail_header_footer_translation fk.mail_header_footer_translation.mail_header_footer_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mail_header_footer_translation
    ADD CONSTRAINT "fk.mail_header_footer_translation.mail_header_footer_id" FOREIGN KEY (mail_header_footer_id) REFERENCES public.mail_header_footer(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: mail_template fk.mail_template.mail_template_type_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mail_template
    ADD CONSTRAINT "fk.mail_template.mail_template_type_id" FOREIGN KEY (mail_template_type_id) REFERENCES public.mail_template_type(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: mail_template_media fk.mail_template_media.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mail_template_media
    ADD CONSTRAINT "fk.mail_template_media.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: mail_template_media fk.mail_template_media.mail_template_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mail_template_media
    ADD CONSTRAINT "fk.mail_template_media.mail_template_id" FOREIGN KEY (mail_template_id) REFERENCES public.mail_template(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: mail_template_media fk.mail_template_media.media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mail_template_media
    ADD CONSTRAINT "fk.mail_template_media.media_id" FOREIGN KEY (media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: mail_template_translation fk.mail_template_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mail_template_translation
    ADD CONSTRAINT "fk.mail_template_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: mail_template_translation fk.mail_template_translation.mail_template_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mail_template_translation
    ADD CONSTRAINT "fk.mail_template_translation.mail_template_id" FOREIGN KEY (mail_template_id) REFERENCES public.mail_template(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: mail_template_type_translation fk.mail_template_type_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mail_template_type_translation
    ADD CONSTRAINT "fk.mail_template_type_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: mail_template_type_translation fk.mail_template_type_translation.mail_template_type_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mail_template_type_translation
    ADD CONSTRAINT "fk.mail_template_type_translation.mail_template_type_id" FOREIGN KEY (mail_template_type_id) REFERENCES public.mail_template_type(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: main_category fk.main_category.category_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.main_category
    ADD CONSTRAINT "fk.main_category.category_id" FOREIGN KEY (category_id, category_version_id) REFERENCES public.category(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: main_category fk.main_category.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.main_category
    ADD CONSTRAINT "fk.main_category.product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: main_category fk.main_category.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.main_category
    ADD CONSTRAINT "fk.main_category.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: measurement_display_unit fk.measurement_display_unit.measurement_system_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.measurement_display_unit
    ADD CONSTRAINT "fk.measurement_display_unit.measurement_system_id" FOREIGN KEY (measurement_system_id) REFERENCES public.measurement_system(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: measurement_display_unit_translation fk.measurement_display_unit_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.measurement_display_unit_translation
    ADD CONSTRAINT "fk.measurement_display_unit_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: measurement_display_unit_translation fk.measurement_display_unit_translation.unit_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.measurement_display_unit_translation
    ADD CONSTRAINT "fk.measurement_display_unit_translation.unit_id" FOREIGN KEY (measurement_display_unit_id) REFERENCES public.measurement_display_unit(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: measurement_system_translation fk.measurement_system_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.measurement_system_translation
    ADD CONSTRAINT "fk.measurement_system_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: measurement_system_translation fk.measurement_system_translation.measurement_system_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.measurement_system_translation
    ADD CONSTRAINT "fk.measurement_system_translation.measurement_system_id" FOREIGN KEY (measurement_system_id) REFERENCES public.measurement_system(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: media fk.media.media_folder_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media
    ADD CONSTRAINT "fk.media.media_folder_id" FOREIGN KEY (media_folder_id) REFERENCES public.media_folder(id) ON DELETE SET NULL;


--
-- Name: media fk.media.user_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media
    ADD CONSTRAINT "fk.media.user_id" FOREIGN KEY (user_id) REFERENCES public."user"(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: media_folder fk.media_folder.default_folder_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_folder
    ADD CONSTRAINT "fk.media_folder.default_folder_id" FOREIGN KEY (default_folder_id) REFERENCES public.media_default_folder(id) ON DELETE SET NULL;


--
-- Name: media_folder fk.media_folder.parent_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_folder
    ADD CONSTRAINT "fk.media_folder.parent_id" FOREIGN KEY (parent_id) REFERENCES public.media_folder(id) ON DELETE CASCADE;


--
-- Name: media_folder_configuration_media_thumbnail_size fk.media_folder_configuration_media_thumbnail_size.conf_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_folder_configuration_media_thumbnail_size
    ADD CONSTRAINT "fk.media_folder_configuration_media_thumbnail_size.conf_id" FOREIGN KEY (media_folder_configuration_id) REFERENCES public.media_folder_configuration(id) ON DELETE CASCADE;


--
-- Name: media_folder_configuration_media_thumbnail_size fk.media_folder_configuration_media_thumbnail_size.size_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_folder_configuration_media_thumbnail_size
    ADD CONSTRAINT "fk.media_folder_configuration_media_thumbnail_size.size_id" FOREIGN KEY (media_thumbnail_size_id) REFERENCES public.media_thumbnail_size(id) ON DELETE CASCADE;


--
-- Name: media_tag fk.media_tag.media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_tag
    ADD CONSTRAINT "fk.media_tag.media_id" FOREIGN KEY (media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: media_tag fk.media_tag.tag_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_tag
    ADD CONSTRAINT "fk.media_tag.tag_id" FOREIGN KEY (tag_id) REFERENCES public.tag(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: media_thumbnail fk.media_thumbnail.media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_thumbnail
    ADD CONSTRAINT "fk.media_thumbnail.media_id" FOREIGN KEY (media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: media_thumbnail fk.media_thumbnail.media_thumbnail_size_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_thumbnail
    ADD CONSTRAINT "fk.media_thumbnail.media_thumbnail_size_id" FOREIGN KEY (media_thumbnail_size_id) REFERENCES public.media_thumbnail_size(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: media_translation fk.media_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_translation
    ADD CONSTRAINT "fk.media_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: media_translation fk.media_translation.media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media_translation
    ADD CONSTRAINT "fk.media_translation.media_id" FOREIGN KEY (media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: newsletter_recipient fk.newsletter_recipient.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.newsletter_recipient
    ADD CONSTRAINT "fk.newsletter_recipient.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON DELETE RESTRICT;


--
-- Name: newsletter_recipient fk.newsletter_recipient.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.newsletter_recipient
    ADD CONSTRAINT "fk.newsletter_recipient.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON DELETE RESTRICT;


--
-- Name: newsletter_recipient fk.newsletter_recipient.salutation_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.newsletter_recipient
    ADD CONSTRAINT "fk.newsletter_recipient.salutation_id" FOREIGN KEY (salutation_id) REFERENCES public.salutation(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: newsletter_recipient_tag fk.newsletter_recipient_tag.newsletter_recipient_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.newsletter_recipient_tag
    ADD CONSTRAINT "fk.newsletter_recipient_tag.newsletter_recipient_id" FOREIGN KEY (newsletter_recipient_id) REFERENCES public.newsletter_recipient(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: newsletter_recipient_tag fk.newsletter_recipient_tag.tag_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.newsletter_recipient_tag
    ADD CONSTRAINT "fk.newsletter_recipient_tag.tag_id" FOREIGN KEY (tag_id) REFERENCES public.tag(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: notification fk.notification.created_by_integration_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification
    ADD CONSTRAINT "fk.notification.created_by_integration_id" FOREIGN KEY (created_by_integration_id) REFERENCES public.integration(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: notification fk.notification.created_by_user_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification
    ADD CONSTRAINT "fk.notification.created_by_user_id" FOREIGN KEY (created_by_user_id) REFERENCES public."user"(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: number_range_sales_channel fk.number_range_sales_channel.number_range_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_range_sales_channel
    ADD CONSTRAINT "fk.number_range_sales_channel.number_range_id" FOREIGN KEY (number_range_id) REFERENCES public.number_range(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: number_range_sales_channel fk.number_range_sales_channel.number_range_type_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_range_sales_channel
    ADD CONSTRAINT "fk.number_range_sales_channel.number_range_type_id" FOREIGN KEY (number_range_type_id) REFERENCES public.number_range_type(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: number_range_sales_channel fk.number_range_sales_channel.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_range_sales_channel
    ADD CONSTRAINT "fk.number_range_sales_channel.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: number_range_translation fk.number_range_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_range_translation
    ADD CONSTRAINT "fk.number_range_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: number_range_translation fk.number_range_translation.number_range_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_range_translation
    ADD CONSTRAINT "fk.number_range_translation.number_range_id" FOREIGN KEY (number_range_id) REFERENCES public.number_range(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: number_range_type_translation fk.number_range_type_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_range_type_translation
    ADD CONSTRAINT "fk.number_range_type_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: number_range_type_translation fk.number_range_type_translation.number_range_type_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_range_type_translation
    ADD CONSTRAINT "fk.number_range_type_translation.number_range_type_id" FOREIGN KEY (number_range_type_id) REFERENCES public.number_range_type(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: oauth_user fk.oauth_user.user_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_user
    ADD CONSTRAINT "fk.oauth_user.user_id" FOREIGN KEY (user_id) REFERENCES public."user"(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order fk.order.created_by_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public."order"
    ADD CONSTRAINT "fk.order.created_by_id" FOREIGN KEY (created_by_id) REFERENCES public."user"(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: order fk.order.currency_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public."order"
    ADD CONSTRAINT "fk.order.currency_id" FOREIGN KEY (currency_id) REFERENCES public.currency(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: order fk.order.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public."order"
    ADD CONSTRAINT "fk.order.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: order fk.order.state_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public."order"
    ADD CONSTRAINT "fk.order.state_id" FOREIGN KEY (state_id) REFERENCES public.state_machine_state(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: order fk.order.updated_by_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public."order"
    ADD CONSTRAINT "fk.order.updated_by_id" FOREIGN KEY (updated_by_id) REFERENCES public."user"(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: order_address fk.order_address.country_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_address
    ADD CONSTRAINT "fk.order_address.country_id" FOREIGN KEY (country_id) REFERENCES public.country(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: order_address fk.order_address.country_state_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_address
    ADD CONSTRAINT "fk.order_address.country_state_id" FOREIGN KEY (country_state_id) REFERENCES public.country_state(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: order_address fk.order_address.order_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_address
    ADD CONSTRAINT "fk.order_address.order_id" FOREIGN KEY (order_id, order_version_id) REFERENCES public."order"(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order_address fk.order_address.salutation_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_address
    ADD CONSTRAINT "fk.order_address.salutation_id" FOREIGN KEY (salutation_id) REFERENCES public.salutation(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: order_customer fk.order_customer.customer_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_customer
    ADD CONSTRAINT "fk.order_customer.customer_id" FOREIGN KEY (customer_id) REFERENCES public.customer(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: order_customer fk.order_customer.order_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_customer
    ADD CONSTRAINT "fk.order_customer.order_id" FOREIGN KEY (order_id, order_version_id) REFERENCES public."order"(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order_customer fk.order_customer.salutation_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_customer
    ADD CONSTRAINT "fk.order_customer.salutation_id" FOREIGN KEY (salutation_id) REFERENCES public.salutation(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: order_delivery fk.order_delivery.order_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_delivery
    ADD CONSTRAINT "fk.order_delivery.order_id" FOREIGN KEY (order_id, order_version_id) REFERENCES public."order"(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order_delivery fk.order_delivery.shipping_method_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_delivery
    ADD CONSTRAINT "fk.order_delivery.shipping_method_id" FOREIGN KEY (shipping_method_id) REFERENCES public.shipping_method(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: order_delivery fk.order_delivery.shipping_order_address_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_delivery
    ADD CONSTRAINT "fk.order_delivery.shipping_order_address_id" FOREIGN KEY (shipping_order_address_id, shipping_order_address_version_id) REFERENCES public.order_address(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order_delivery fk.order_delivery.state_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_delivery
    ADD CONSTRAINT "fk.order_delivery.state_id" FOREIGN KEY (state_id) REFERENCES public.state_machine_state(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: order_delivery_position fk.order_delivery_position.order_delivery_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_delivery_position
    ADD CONSTRAINT "fk.order_delivery_position.order_delivery_id" FOREIGN KEY (order_delivery_id, order_delivery_version_id) REFERENCES public.order_delivery(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order_delivery_position fk.order_delivery_position.order_line_item_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_delivery_position
    ADD CONSTRAINT "fk.order_delivery_position.order_line_item_id" FOREIGN KEY (order_line_item_id, order_line_item_version_id) REFERENCES public.order_line_item(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order_line_item fk.order_line_item.cover_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_line_item
    ADD CONSTRAINT "fk.order_line_item.cover_id" FOREIGN KEY (cover_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: order_line_item fk.order_line_item.order_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_line_item
    ADD CONSTRAINT "fk.order_line_item.order_id" FOREIGN KEY (order_id, order_version_id) REFERENCES public."order"(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order_line_item fk.order_line_item.parent_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_line_item
    ADD CONSTRAINT "fk.order_line_item.parent_id" FOREIGN KEY (parent_id, parent_version_id) REFERENCES public.order_line_item(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order_line_item fk.order_line_item.product; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_line_item
    ADD CONSTRAINT "fk.order_line_item.product" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: order_line_item fk.order_line_item.promotion_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_line_item
    ADD CONSTRAINT "fk.order_line_item.promotion_id" FOREIGN KEY (promotion_id) REFERENCES public.promotion(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: order_line_item_download fk.order_line_item_download.media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_line_item_download
    ADD CONSTRAINT "fk.order_line_item_download.media_id" FOREIGN KEY (media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: order_line_item_download fk.order_line_item_download.order_line_item_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_line_item_download
    ADD CONSTRAINT "fk.order_line_item_download.order_line_item_id" FOREIGN KEY (order_line_item_id, order_line_item_version_id) REFERENCES public.order_line_item(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order_tag fk.order_tag.order_tag__order_version_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_tag
    ADD CONSTRAINT "fk.order_tag.order_tag__order_version_id" FOREIGN KEY (order_id, order_version_id) REFERENCES public."order"(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order_tag fk.order_tag.tag_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_tag
    ADD CONSTRAINT "fk.order_tag.tag_id" FOREIGN KEY (tag_id) REFERENCES public.tag(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order_transaction fk.order_transaction.order_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_transaction
    ADD CONSTRAINT "fk.order_transaction.order_id" FOREIGN KEY (order_id, order_version_id) REFERENCES public."order"(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order_transaction fk.order_transaction.payment_method_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_transaction
    ADD CONSTRAINT "fk.order_transaction.payment_method_id" FOREIGN KEY (payment_method_id) REFERENCES public.payment_method(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: order_transaction fk.order_transaction.state_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_transaction
    ADD CONSTRAINT "fk.order_transaction.state_id" FOREIGN KEY (state_id) REFERENCES public.state_machine_state(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: order_transaction_capture fk.order_transaction_capture.order_transaction_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_transaction_capture
    ADD CONSTRAINT "fk.order_transaction_capture.order_transaction_id" FOREIGN KEY (order_transaction_id, order_transaction_version_id) REFERENCES public.order_transaction(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order_transaction_capture fk.order_transaction_capture.state_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_transaction_capture
    ADD CONSTRAINT "fk.order_transaction_capture.state_id" FOREIGN KEY (state_id) REFERENCES public.state_machine_state(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: order_transaction_capture_refund fk.order_transaction_capture_refund.capture_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_transaction_capture_refund
    ADD CONSTRAINT "fk.order_transaction_capture_refund.capture_id" FOREIGN KEY (capture_id, capture_version_id) REFERENCES public.order_transaction_capture(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order_transaction_capture_refund fk.order_transaction_capture_refund.state_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_transaction_capture_refund
    ADD CONSTRAINT "fk.order_transaction_capture_refund.state_id" FOREIGN KEY (state_id) REFERENCES public.state_machine_state(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: order_transaction_capture_refund_position fk.order_transaction_capture_refund_position.order_line_item_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_transaction_capture_refund_position
    ADD CONSTRAINT "fk.order_transaction_capture_refund_position.order_line_item_id" FOREIGN KEY (order_line_item_id, order_line_item_version_id) REFERENCES public.order_line_item(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order_transaction_capture_refund_position fk.order_transaction_capture_refund_position.refund_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_transaction_capture_refund_position
    ADD CONSTRAINT "fk.order_transaction_capture_refund_position.refund_id" FOREIGN KEY (refund_id, refund_version_id) REFERENCES public.order_transaction_capture_refund(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: payment_method fk.payment_method.availability_rule_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_method
    ADD CONSTRAINT "fk.payment_method.availability_rule_id" FOREIGN KEY (availability_rule_id) REFERENCES public.rule(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: payment_method fk.payment_method.media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_method
    ADD CONSTRAINT "fk.payment_method.media_id" FOREIGN KEY (media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: payment_method fk.payment_method.plugin_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_method
    ADD CONSTRAINT "fk.payment_method.plugin_id" FOREIGN KEY (plugin_id) REFERENCES public.plugin(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: payment_method_translation fk.payment_method_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_method_translation
    ADD CONSTRAINT "fk.payment_method_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: payment_method_translation fk.payment_method_translation.payment_method_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_method_translation
    ADD CONSTRAINT "fk.payment_method_translation.payment_method_id" FOREIGN KEY (payment_method_id) REFERENCES public.payment_method(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: plugin_translation fk.plugin_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugin_translation
    ADD CONSTRAINT "fk.plugin_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: plugin_translation fk.plugin_translation.plugin_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugin_translation
    ADD CONSTRAINT "fk.plugin_translation.plugin_id" FOREIGN KEY (plugin_id) REFERENCES public.plugin(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product fk.product.canonical_product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product
    ADD CONSTRAINT "fk.product.canonical_product_id" FOREIGN KEY (canonical_product_id, canonical_product_version_id) REFERENCES public.product(id, version_id) ON DELETE SET NULL;


--
-- Name: product fk.product.cms_page_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product
    ADD CONSTRAINT "fk.product.cms_page_id" FOREIGN KEY (cms_page_id, cms_page_version_id) REFERENCES public.cms_page(id, version_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: product fk.product.feature_set_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product
    ADD CONSTRAINT "fk.product.feature_set_id" FOREIGN KEY (product_feature_set_id) REFERENCES public.product_feature_set(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: product fk.product.open_graph_media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product
    ADD CONSTRAINT "fk.product.open_graph_media_id" FOREIGN KEY (open_graph_media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: product fk.product.parent_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product
    ADD CONSTRAINT "fk.product.parent_id" FOREIGN KEY (parent_id, parent_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product fk.product.product_manufacturer; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product
    ADD CONSTRAINT "fk.product.product_manufacturer" FOREIGN KEY (product_manufacturer_id, product_manufacturer_version_id) REFERENCES public.product_manufacturer(id, version_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: product fk.product.tax_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product
    ADD CONSTRAINT "fk.product.tax_id" FOREIGN KEY (tax_id) REFERENCES public.tax(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: product fk.product.unit_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product
    ADD CONSTRAINT "fk.product.unit_id" FOREIGN KEY (unit_id) REFERENCES public.unit(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: product_category fk.product_category.category_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_category
    ADD CONSTRAINT "fk.product_category.category_id" FOREIGN KEY (category_id, category_version_id) REFERENCES public.category(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_category fk.product_category.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_category
    ADD CONSTRAINT "fk.product_category.product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_category_tree fk.product_category_tree.category_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_category_tree
    ADD CONSTRAINT "fk.product_category_tree.category_id" FOREIGN KEY (category_id, category_version_id) REFERENCES public.category(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_category_tree fk.product_category_tree.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_category_tree
    ADD CONSTRAINT "fk.product_category_tree.product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_configurator_setting fk.product_configurator_setting.media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_configurator_setting
    ADD CONSTRAINT "fk.product_configurator_setting.media_id" FOREIGN KEY (media_id) REFERENCES public.media(id) ON DELETE SET NULL;


--
-- Name: product_configurator_setting fk.product_configurator_setting.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_configurator_setting
    ADD CONSTRAINT "fk.product_configurator_setting.product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_configurator_setting fk.product_configurator_setting.property_group_option_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_configurator_setting
    ADD CONSTRAINT "fk.product_configurator_setting.property_group_option_id" FOREIGN KEY (property_group_option_id) REFERENCES public.property_group_option(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: product_cross_selling fk.product_cross_selling.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_cross_selling
    ADD CONSTRAINT "fk.product_cross_selling.product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_cross_selling fk.product_cross_selling.product_stream_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_cross_selling
    ADD CONSTRAINT "fk.product_cross_selling.product_stream_id" FOREIGN KEY (product_stream_id) REFERENCES public.product_stream(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_cross_selling_assigned_products fk.product_cross_selling_assigned_products.cross_selling_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_cross_selling_assigned_products
    ADD CONSTRAINT "fk.product_cross_selling_assigned_products.cross_selling_id" FOREIGN KEY (cross_selling_id) REFERENCES public.product_cross_selling(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_cross_selling_translation fk.product_cross_selling_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_cross_selling_translation
    ADD CONSTRAINT "fk.product_cross_selling_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_cross_selling_translation fk.product_cross_selling_translation.product_cross_selling_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_cross_selling_translation
    ADD CONSTRAINT "fk.product_cross_selling_translation.product_cross_selling_id" FOREIGN KEY (product_cross_selling_id) REFERENCES public.product_cross_selling(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_custom_field_set fk.product_custom_field_set.custom_field_set_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_custom_field_set
    ADD CONSTRAINT "fk.product_custom_field_set.custom_field_set_id" FOREIGN KEY (custom_field_set_id) REFERENCES public.custom_field_set(id) ON DELETE CASCADE;


--
-- Name: product_custom_field_set fk.product_custom_field_set.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_custom_field_set
    ADD CONSTRAINT "fk.product_custom_field_set.product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON DELETE CASCADE;


--
-- Name: product_download fk.product_download.media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_download
    ADD CONSTRAINT "fk.product_download.media_id" FOREIGN KEY (media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: product_download fk.product_download.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_download
    ADD CONSTRAINT "fk.product_download.product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_export fk.product_export.product_stream_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_export
    ADD CONSTRAINT "fk.product_export.product_stream_id" FOREIGN KEY (product_stream_id) REFERENCES public.product_stream(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: product_export fk.product_export.sales_channel_domain_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_export
    ADD CONSTRAINT "fk.product_export.sales_channel_domain_id" FOREIGN KEY (sales_channel_domain_id) REFERENCES public.sales_channel_domain(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: product_export fk.product_export.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_export
    ADD CONSTRAINT "fk.product_export.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_export fk.product_export.storefront_sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_export
    ADD CONSTRAINT "fk.product_export.storefront_sales_channel_id" FOREIGN KEY (storefront_sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_feature_set_translation fk.product_feature_set_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_feature_set_translation
    ADD CONSTRAINT "fk.product_feature_set_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_feature_set_translation fk.product_feature_set_translation.product_feature_set_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_feature_set_translation
    ADD CONSTRAINT "fk.product_feature_set_translation.product_feature_set_id" FOREIGN KEY (product_feature_set_id) REFERENCES public.product_feature_set(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_keyword_dictionary fk.product_keyword_dictionary.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_keyword_dictionary
    ADD CONSTRAINT "fk.product_keyword_dictionary.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_manufacturer fk.product_manufacturer.media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_manufacturer
    ADD CONSTRAINT "fk.product_manufacturer.media_id" FOREIGN KEY (media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: product_manufacturer_translation fk.product_manufacturer_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_manufacturer_translation
    ADD CONSTRAINT "fk.product_manufacturer_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_manufacturer_translation fk.product_manufacturer_translation.product_manufacturer_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_manufacturer_translation
    ADD CONSTRAINT "fk.product_manufacturer_translation.product_manufacturer_id" FOREIGN KEY (product_manufacturer_id, product_manufacturer_version_id) REFERENCES public.product_manufacturer(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_media fk.product_media.media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_media
    ADD CONSTRAINT "fk.product_media.media_id" FOREIGN KEY (media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_media fk.product_media.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_media
    ADD CONSTRAINT "fk.product_media.product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_option fk.product_option.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_option
    ADD CONSTRAINT "fk.product_option.product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_option fk.product_option.property_group_option_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_option
    ADD CONSTRAINT "fk.product_option.property_group_option_id" FOREIGN KEY (property_group_option_id) REFERENCES public.property_group_option(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_price fk.product_price.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_price
    ADD CONSTRAINT "fk.product_price.product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_price fk.product_price.rule_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_price
    ADD CONSTRAINT "fk.product_price.rule_id" FOREIGN KEY (rule_id) REFERENCES public.rule(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: product_property fk.product_property.configuration_option_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_property
    ADD CONSTRAINT "fk.product_property.configuration_option_id" FOREIGN KEY (property_group_option_id) REFERENCES public.property_group_option(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_property fk.product_property.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_property
    ADD CONSTRAINT "fk.product_property.product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_review fk.product_review.customer_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_review
    ADD CONSTRAINT "fk.product_review.customer_id" FOREIGN KEY (customer_id) REFERENCES public.customer(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: product_review fk.product_review.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_review
    ADD CONSTRAINT "fk.product_review.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_review fk.product_review.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_review
    ADD CONSTRAINT "fk.product_review.product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_review fk.product_review.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_review
    ADD CONSTRAINT "fk.product_review.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id);


--
-- Name: product_search_config fk.product_search_config.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_search_config
    ADD CONSTRAINT "fk.product_search_config.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_search_keyword fk.product_search_keyword.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_search_keyword
    ADD CONSTRAINT "fk.product_search_keyword.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_search_keyword fk.product_search_keyword.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_search_keyword
    ADD CONSTRAINT "fk.product_search_keyword.product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_sorting_translation fk.product_sorting_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_sorting_translation
    ADD CONSTRAINT "fk.product_sorting_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_sorting_translation fk.product_sorting_translation.product_sorting_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_sorting_translation
    ADD CONSTRAINT "fk.product_sorting_translation.product_sorting_id" FOREIGN KEY (product_sorting_id) REFERENCES public.product_sorting(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_stream_filter fk.product_stream_filter.parent_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_stream_filter
    ADD CONSTRAINT "fk.product_stream_filter.parent_id" FOREIGN KEY (parent_id) REFERENCES public.product_stream_filter(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_stream_filter fk.product_stream_filter.product_stream_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_stream_filter
    ADD CONSTRAINT "fk.product_stream_filter.product_stream_id" FOREIGN KEY (product_stream_id) REFERENCES public.product_stream(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_stream_mapping fk.product_stream_mapping.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_stream_mapping
    ADD CONSTRAINT "fk.product_stream_mapping.product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_stream_mapping fk.product_stream_mapping.product_stream_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_stream_mapping
    ADD CONSTRAINT "fk.product_stream_mapping.product_stream_id" FOREIGN KEY (product_stream_id) REFERENCES public.product_stream(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_stream_translation fk.product_stream_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_stream_translation
    ADD CONSTRAINT "fk.product_stream_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_stream_translation fk.product_stream_translation.product_stream_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_stream_translation
    ADD CONSTRAINT "fk.product_stream_translation.product_stream_id" FOREIGN KEY (product_stream_id) REFERENCES public.product_stream(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_tag fk.product_tag.product_version_id__product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_tag
    ADD CONSTRAINT "fk.product_tag.product_version_id__product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_tag fk.product_tag.tag_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_tag
    ADD CONSTRAINT "fk.product_tag.tag_id" FOREIGN KEY (tag_id) REFERENCES public.tag(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_translation fk.product_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_translation
    ADD CONSTRAINT "fk.product_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_translation fk.product_translation.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_translation
    ADD CONSTRAINT "fk.product_translation.product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_visibility fk.product_visibility.product_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_visibility
    ADD CONSTRAINT "fk.product_visibility.product_id" FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_visibility fk.product_visibility.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_visibility
    ADD CONSTRAINT "fk.product_visibility.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: promotion_cart_rule fk.promotion_cart_rule.promotion_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_cart_rule
    ADD CONSTRAINT "fk.promotion_cart_rule.promotion_id" FOREIGN KEY (promotion_id) REFERENCES public.promotion(id) ON DELETE CASCADE;


--
-- Name: promotion_cart_rule fk.promotion_cart_rule.rule_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_cart_rule
    ADD CONSTRAINT "fk.promotion_cart_rule.rule_id" FOREIGN KEY (rule_id) REFERENCES public.rule(id) ON DELETE CASCADE;


--
-- Name: promotion_discount fk.promotion_discount.promotion_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_discount
    ADD CONSTRAINT "fk.promotion_discount.promotion_id" FOREIGN KEY (promotion_id) REFERENCES public.promotion(id) ON DELETE CASCADE;


--
-- Name: promotion_discount_prices fk.promotion_discount_prices.currency_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_discount_prices
    ADD CONSTRAINT "fk.promotion_discount_prices.currency_id" FOREIGN KEY (currency_id) REFERENCES public.currency(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: promotion_discount_prices fk.promotion_discount_prices.discount_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_discount_prices
    ADD CONSTRAINT "fk.promotion_discount_prices.discount_id" FOREIGN KEY (discount_id) REFERENCES public.promotion_discount(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: promotion_discount_rule fk.promotion_discount_rule.promotion_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_discount_rule
    ADD CONSTRAINT "fk.promotion_discount_rule.promotion_id" FOREIGN KEY (discount_id) REFERENCES public.promotion_discount(id) ON DELETE CASCADE;


--
-- Name: promotion_discount_rule fk.promotion_discount_rule.rule_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_discount_rule
    ADD CONSTRAINT "fk.promotion_discount_rule.rule_id" FOREIGN KEY (rule_id) REFERENCES public.rule(id) ON DELETE CASCADE;


--
-- Name: promotion_individual_code fk.promotion_individual_code.promotion_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_individual_code
    ADD CONSTRAINT "fk.promotion_individual_code.promotion_id" FOREIGN KEY (promotion_id) REFERENCES public.promotion(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: promotion_order_rule fk.promotion_order_rule.promotion_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_order_rule
    ADD CONSTRAINT "fk.promotion_order_rule.promotion_id" FOREIGN KEY (promotion_id) REFERENCES public.promotion(id) ON DELETE CASCADE;


--
-- Name: promotion_order_rule fk.promotion_order_rule.rule_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_order_rule
    ADD CONSTRAINT "fk.promotion_order_rule.rule_id" FOREIGN KEY (rule_id) REFERENCES public.rule(id) ON DELETE CASCADE;


--
-- Name: promotion_persona_customer fk.promotion_persona_customer.customer_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_persona_customer
    ADD CONSTRAINT "fk.promotion_persona_customer.customer_id" FOREIGN KEY (customer_id) REFERENCES public.customer(id) ON DELETE CASCADE;


--
-- Name: promotion_persona_customer fk.promotion_persona_customer.promotion_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_persona_customer
    ADD CONSTRAINT "fk.promotion_persona_customer.promotion_id" FOREIGN KEY (promotion_id) REFERENCES public.promotion(id) ON DELETE CASCADE;


--
-- Name: promotion_persona_rule fk.promotion_persona_rule.promotion_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_persona_rule
    ADD CONSTRAINT "fk.promotion_persona_rule.promotion_id" FOREIGN KEY (promotion_id) REFERENCES public.promotion(id) ON DELETE CASCADE;


--
-- Name: promotion_persona_rule fk.promotion_persona_rule.rule_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_persona_rule
    ADD CONSTRAINT "fk.promotion_persona_rule.rule_id" FOREIGN KEY (rule_id) REFERENCES public.rule(id) ON DELETE CASCADE;


--
-- Name: promotion_sales_channel fk.promotion_sales_channel.promotion_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_sales_channel
    ADD CONSTRAINT "fk.promotion_sales_channel.promotion_id" FOREIGN KEY (promotion_id) REFERENCES public.promotion(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: promotion_sales_channel fk.promotion_sales_channel.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_sales_channel
    ADD CONSTRAINT "fk.promotion_sales_channel.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: promotion_setgroup fk.promotion_setgroup.promotion_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_setgroup
    ADD CONSTRAINT "fk.promotion_setgroup.promotion_id" FOREIGN KEY (promotion_id) REFERENCES public.promotion(id) ON DELETE CASCADE;


--
-- Name: promotion_setgroup_rule fk.promotion_setgroup_rule.rule_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_setgroup_rule
    ADD CONSTRAINT "fk.promotion_setgroup_rule.rule_id" FOREIGN KEY (rule_id) REFERENCES public.rule(id) ON DELETE CASCADE;


--
-- Name: promotion_setgroup_rule fk.promotion_setgroup_rule.setgroup_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_setgroup_rule
    ADD CONSTRAINT "fk.promotion_setgroup_rule.setgroup_id" FOREIGN KEY (setgroup_id) REFERENCES public.promotion_setgroup(id) ON DELETE CASCADE;


--
-- Name: promotion_translation fk.promotion_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_translation
    ADD CONSTRAINT "fk.promotion_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: promotion_translation fk.promotion_translation.promotion_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotion_translation
    ADD CONSTRAINT "fk.promotion_translation.promotion_id" FOREIGN KEY (promotion_id) REFERENCES public.promotion(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: property_group_option fk.property_group_option.media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.property_group_option
    ADD CONSTRAINT "fk.property_group_option.media_id" FOREIGN KEY (media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: property_group_option fk.property_group_option.property_group_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.property_group_option
    ADD CONSTRAINT "fk.property_group_option.property_group_id" FOREIGN KEY (property_group_id) REFERENCES public.property_group(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: property_group_option_translation fk.property_group_option_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.property_group_option_translation
    ADD CONSTRAINT "fk.property_group_option_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: property_group_option_translation fk.property_group_option_translation.prop_group_option_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.property_group_option_translation
    ADD CONSTRAINT "fk.property_group_option_translation.prop_group_option_id" FOREIGN KEY (property_group_option_id) REFERENCES public.property_group_option(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: property_group_translation fk.property_group_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.property_group_translation
    ADD CONSTRAINT "fk.property_group_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: property_group_translation fk.property_group_translation.property_group_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.property_group_translation
    ADD CONSTRAINT "fk.property_group_translation.property_group_id" FOREIGN KEY (property_group_id) REFERENCES public.property_group(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: rule_condition fk.rule_condition.parent_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rule_condition
    ADD CONSTRAINT "fk.rule_condition.parent_id" FOREIGN KEY (parent_id) REFERENCES public.rule_condition(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: rule_condition fk.rule_condition.rule_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rule_condition
    ADD CONSTRAINT "fk.rule_condition.rule_id" FOREIGN KEY (rule_id) REFERENCES public.rule(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: rule_condition fk.rule_condition.script_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rule_condition
    ADD CONSTRAINT "fk.rule_condition.script_id" FOREIGN KEY (script_id) REFERENCES public.app_script_condition(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: rule_tag fk.rule_tag.id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rule_tag
    ADD CONSTRAINT "fk.rule_tag.id" FOREIGN KEY (rule_id) REFERENCES public.rule(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: rule_tag fk.rule_tag.tag_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rule_tag
    ADD CONSTRAINT "fk.rule_tag.tag_id" FOREIGN KEY (tag_id) REFERENCES public.tag(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel fk.sales_channel.analytics_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel
    ADD CONSTRAINT "fk.sales_channel.analytics_id" FOREIGN KEY (analytics_id) REFERENCES public.sales_channel_analytics(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: sales_channel fk.sales_channel.country_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel
    ADD CONSTRAINT "fk.sales_channel.country_id" FOREIGN KEY (country_id) REFERENCES public.country(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: sales_channel fk.sales_channel.currency_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel
    ADD CONSTRAINT "fk.sales_channel.currency_id" FOREIGN KEY (currency_id) REFERENCES public.currency(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: sales_channel fk.sales_channel.customer_group_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel
    ADD CONSTRAINT "fk.sales_channel.customer_group_id" FOREIGN KEY (customer_group_id) REFERENCES public.customer_group(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: sales_channel fk.sales_channel.footer_category_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel
    ADD CONSTRAINT "fk.sales_channel.footer_category_id" FOREIGN KEY (footer_category_id, footer_category_version_id) REFERENCES public.category(id, version_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: sales_channel fk.sales_channel.header_footer_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel
    ADD CONSTRAINT "fk.sales_channel.header_footer_id" FOREIGN KEY (mail_header_footer_id) REFERENCES public.mail_header_footer(id) ON UPDATE CASCADE;


--
-- Name: sales_channel fk.sales_channel.home_cms_page; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel
    ADD CONSTRAINT "fk.sales_channel.home_cms_page" FOREIGN KEY (home_cms_page_id, home_cms_page_version_id) REFERENCES public.cms_page(id, version_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: sales_channel fk.sales_channel.hreflang_default_domain_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel
    ADD CONSTRAINT "fk.sales_channel.hreflang_default_domain_id" FOREIGN KEY (hreflang_default_domain_id) REFERENCES public.sales_channel_domain(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel fk.sales_channel.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel
    ADD CONSTRAINT "fk.sales_channel.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: sales_channel fk.sales_channel.navigation_category_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel
    ADD CONSTRAINT "fk.sales_channel.navigation_category_id" FOREIGN KEY (navigation_category_id, navigation_category_version_id) REFERENCES public.category(id, version_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: sales_channel fk.sales_channel.payment_method_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel
    ADD CONSTRAINT "fk.sales_channel.payment_method_id" FOREIGN KEY (payment_method_id) REFERENCES public.payment_method(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: sales_channel fk.sales_channel.service_category_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel
    ADD CONSTRAINT "fk.sales_channel.service_category_id" FOREIGN KEY (service_category_id, service_category_version_id) REFERENCES public.category(id, version_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: sales_channel fk.sales_channel.shipping_method_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel
    ADD CONSTRAINT "fk.sales_channel.shipping_method_id" FOREIGN KEY (shipping_method_id) REFERENCES public.shipping_method(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: sales_channel fk.sales_channel.type_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel
    ADD CONSTRAINT "fk.sales_channel.type_id" FOREIGN KEY (type_id) REFERENCES public.sales_channel_type(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: sales_channel_api_context fk.sales_channel_api_context.customer_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_api_context
    ADD CONSTRAINT "fk.sales_channel_api_context.customer_id" FOREIGN KEY (customer_id) REFERENCES public.customer(id) ON DELETE CASCADE;


--
-- Name: sales_channel_api_context fk.sales_channel_api_context.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_api_context
    ADD CONSTRAINT "fk.sales_channel_api_context.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON DELETE CASCADE;


--
-- Name: sales_channel_country fk.sales_channel_country.country_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_country
    ADD CONSTRAINT "fk.sales_channel_country.country_id" FOREIGN KEY (country_id) REFERENCES public.country(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_country fk.sales_channel_country.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_country
    ADD CONSTRAINT "fk.sales_channel_country.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_currency fk.sales_channel_currency.currency_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_currency
    ADD CONSTRAINT "fk.sales_channel_currency.currency_id" FOREIGN KEY (currency_id) REFERENCES public.currency(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_currency fk.sales_channel_currency.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_currency
    ADD CONSTRAINT "fk.sales_channel_currency.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_domain fk.sales_channel_domain.currency_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_domain
    ADD CONSTRAINT "fk.sales_channel_domain.currency_id" FOREIGN KEY (currency_id) REFERENCES public.currency(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: sales_channel_domain fk.sales_channel_domain.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_domain
    ADD CONSTRAINT "fk.sales_channel_domain.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: sales_channel_domain fk.sales_channel_domain.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_domain
    ADD CONSTRAINT "fk.sales_channel_domain.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_domain fk.sales_channel_domain.snippet_set_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_domain
    ADD CONSTRAINT "fk.sales_channel_domain.snippet_set_id" FOREIGN KEY (snippet_set_id) REFERENCES public.snippet_set(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: sales_channel_file fk.sales_channel_file.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_file
    ADD CONSTRAINT "fk.sales_channel_file.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_language fk.sales_channel_language.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_language
    ADD CONSTRAINT "fk.sales_channel_language.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_language fk.sales_channel_language.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_language
    ADD CONSTRAINT "fk.sales_channel_language.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_payment_method fk.sales_channel_payment_method.payment_method_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_payment_method
    ADD CONSTRAINT "fk.sales_channel_payment_method.payment_method_id" FOREIGN KEY (payment_method_id) REFERENCES public.payment_method(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_payment_method fk.sales_channel_payment_method.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_payment_method
    ADD CONSTRAINT "fk.sales_channel_payment_method.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_shipping_method fk.sales_channel_shipping_method.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_shipping_method
    ADD CONSTRAINT "fk.sales_channel_shipping_method.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_shipping_method fk.sales_channel_shipping_method.shipping_method_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_shipping_method
    ADD CONSTRAINT "fk.sales_channel_shipping_method.shipping_method_id" FOREIGN KEY (shipping_method_id) REFERENCES public.shipping_method(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_translation fk.sales_channel_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_translation
    ADD CONSTRAINT "fk.sales_channel_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_translation fk.sales_channel_translation.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_translation
    ADD CONSTRAINT "fk.sales_channel_translation.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_type_translation fk.sales_channel_type_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_type_translation
    ADD CONSTRAINT "fk.sales_channel_type_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_type_translation fk.sales_channel_type_translation.sales_channel_type_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_type_translation
    ADD CONSTRAINT "fk.sales_channel_type_translation.sales_channel_type_id" FOREIGN KEY (sales_channel_type_id) REFERENCES public.sales_channel_type(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: salutation_translation fk.salutation_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salutation_translation
    ADD CONSTRAINT "fk.salutation_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: salutation_translation fk.salutation_translation.salutation_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salutation_translation
    ADD CONSTRAINT "fk.salutation_translation.salutation_id" FOREIGN KEY (salutation_id) REFERENCES public.salutation(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_tracking_customer fk.sc_tracking_customer.customer_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_tracking_customer
    ADD CONSTRAINT "fk.sc_tracking_customer.customer_id" FOREIGN KEY (customer_id) REFERENCES public.customer(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_tracking_customer fk.sc_tracking_customer.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_tracking_customer
    ADD CONSTRAINT "fk.sc_tracking_customer.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_tracking_order fk.sc_tracking_order.order_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_tracking_order
    ADD CONSTRAINT "fk.sc_tracking_order.order_id" FOREIGN KEY (order_id, order_version_id) REFERENCES public."order"(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_channel_tracking_order fk.sc_tracking_order.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_channel_tracking_order
    ADD CONSTRAINT "fk.sc_tracking_order.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_search_config_field fk.search_config_field.custom_field_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_search_config_field
    ADD CONSTRAINT "fk.search_config_field.custom_field_id" FOREIGN KEY (custom_field_id) REFERENCES public.custom_field(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_search_config_field fk.search_config_field.product_search_config_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_search_config_field
    ADD CONSTRAINT "fk.search_config_field.product_search_config_id" FOREIGN KEY (product_search_config_id) REFERENCES public.product_search_config(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: seo_url fk.seo_url.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.seo_url
    ADD CONSTRAINT "fk.seo_url.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: seo_url fk.seo_url.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.seo_url
    ADD CONSTRAINT "fk.seo_url.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: seo_url_template fk.seo_url_template.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.seo_url_template
    ADD CONSTRAINT "fk.seo_url_template.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: shipping_method fk.shipping_method.availability_rule_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_method
    ADD CONSTRAINT "fk.shipping_method.availability_rule_id" FOREIGN KEY (availability_rule_id) REFERENCES public.rule(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: shipping_method fk.shipping_method.delivery_time_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_method
    ADD CONSTRAINT "fk.shipping_method.delivery_time_id" FOREIGN KEY (delivery_time_id) REFERENCES public.delivery_time(id);


--
-- Name: shipping_method fk.shipping_method.media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_method
    ADD CONSTRAINT "fk.shipping_method.media_id" FOREIGN KEY (media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: shipping_method fk.shipping_method.tax_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_method
    ADD CONSTRAINT "fk.shipping_method.tax_id" FOREIGN KEY (tax_id) REFERENCES public.tax(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: shipping_method_price fk.shipping_method_price.calculation_rule_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_method_price
    ADD CONSTRAINT "fk.shipping_method_price.calculation_rule_id" FOREIGN KEY (calculation_rule_id) REFERENCES public.rule(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: shipping_method_price fk.shipping_method_price.rule_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_method_price
    ADD CONSTRAINT "fk.shipping_method_price.rule_id" FOREIGN KEY (rule_id) REFERENCES public.rule(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: shipping_method_price fk.shipping_method_price.shipping_method_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_method_price
    ADD CONSTRAINT "fk.shipping_method_price.shipping_method_id" FOREIGN KEY (shipping_method_id) REFERENCES public.shipping_method(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: shipping_method_tag fk.shipping_method_tag.shipping_method_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_method_tag
    ADD CONSTRAINT "fk.shipping_method_tag.shipping_method_id" FOREIGN KEY (shipping_method_id) REFERENCES public.shipping_method(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: shipping_method_tag fk.shipping_method_tag.tag_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_method_tag
    ADD CONSTRAINT "fk.shipping_method_tag.tag_id" FOREIGN KEY (tag_id) REFERENCES public.tag(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: shipping_method_translation fk.shipping_method_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_method_translation
    ADD CONSTRAINT "fk.shipping_method_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: shipping_method_translation fk.shipping_method_translation.shipping_method_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_method_translation
    ADD CONSTRAINT "fk.shipping_method_translation.shipping_method_id" FOREIGN KEY (shipping_method_id) REFERENCES public.shipping_method(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: snippet fk.snippet.snippet_set_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.snippet
    ADD CONSTRAINT "fk.snippet.snippet_set_id" FOREIGN KEY (snippet_set_id) REFERENCES public.snippet_set(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: state_machine fk.state_machine.initial_state_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine
    ADD CONSTRAINT "fk.state_machine.initial_state_id" FOREIGN KEY (initial_state_id) REFERENCES public.state_machine_state(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: state_machine_history fk.state_machine_history.from_state_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_history
    ADD CONSTRAINT "fk.state_machine_history.from_state_id" FOREIGN KEY (from_state_id) REFERENCES public.state_machine_state(id) ON UPDATE CASCADE;


--
-- Name: state_machine_history fk.state_machine_history.integration_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_history
    ADD CONSTRAINT "fk.state_machine_history.integration_id" FOREIGN KEY (integration_id) REFERENCES public.integration(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: state_machine_history fk.state_machine_history.state_machine_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_history
    ADD CONSTRAINT "fk.state_machine_history.state_machine_id" FOREIGN KEY (state_machine_id) REFERENCES public.state_machine(id) ON UPDATE CASCADE;


--
-- Name: state_machine_history fk.state_machine_history.to_state_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_history
    ADD CONSTRAINT "fk.state_machine_history.to_state_id" FOREIGN KEY (to_state_id) REFERENCES public.state_machine_state(id) ON UPDATE CASCADE;


--
-- Name: state_machine_history fk.state_machine_history.user_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_history
    ADD CONSTRAINT "fk.state_machine_history.user_id" FOREIGN KEY (user_id) REFERENCES public."user"(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: state_machine_state fk.state_machine_state.state_machine_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_state
    ADD CONSTRAINT "fk.state_machine_state.state_machine_id" FOREIGN KEY (state_machine_id) REFERENCES public.state_machine(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: state_machine_state_translation fk.state_machine_state_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_state_translation
    ADD CONSTRAINT "fk.state_machine_state_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: state_machine_state_translation fk.state_machine_state_translation.state_machine_state_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_state_translation
    ADD CONSTRAINT "fk.state_machine_state_translation.state_machine_state_id" FOREIGN KEY (state_machine_state_id) REFERENCES public.state_machine_state(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: state_machine_transition fk.state_machine_transition.from_state_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_transition
    ADD CONSTRAINT "fk.state_machine_transition.from_state_id" FOREIGN KEY (from_state_id) REFERENCES public.state_machine_state(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: state_machine_transition fk.state_machine_transition.state_machine_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_transition
    ADD CONSTRAINT "fk.state_machine_transition.state_machine_id" FOREIGN KEY (state_machine_id) REFERENCES public.state_machine(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: state_machine_transition fk.state_machine_transition.to_state_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_transition
    ADD CONSTRAINT "fk.state_machine_transition.to_state_id" FOREIGN KEY (to_state_id) REFERENCES public.state_machine_state(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: state_machine_translation fk.state_machine_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_translation
    ADD CONSTRAINT "fk.state_machine_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: state_machine_translation fk.state_machine_translation.state_machine_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.state_machine_translation
    ADD CONSTRAINT "fk.state_machine_translation.state_machine_id" FOREIGN KEY (state_machine_id) REFERENCES public.state_machine(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: system_config fk.system_config.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.system_config
    ADD CONSTRAINT "fk.system_config.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: tax_provider fk.tax_provider.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_provider
    ADD CONSTRAINT "fk.tax_provider.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: tax_provider fk.tax_provider.availability_rule_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_provider
    ADD CONSTRAINT "fk.tax_provider.availability_rule_id" FOREIGN KEY (availability_rule_id) REFERENCES public.rule(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: tax_provider_translation fk.tax_provider_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_provider_translation
    ADD CONSTRAINT "fk.tax_provider_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: tax_provider_translation fk.tax_provider_translation.tax_provider_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_provider_translation
    ADD CONSTRAINT "fk.tax_provider_translation.tax_provider_id" FOREIGN KEY (tax_provider_id) REFERENCES public.tax_provider(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: tax_rule fk.tax_rule.country_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_rule
    ADD CONSTRAINT "fk.tax_rule.country_id" FOREIGN KEY (country_id) REFERENCES public.country(id);


--
-- Name: tax_rule fk.tax_rule.tax_area_rule_type_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_rule
    ADD CONSTRAINT "fk.tax_rule.tax_area_rule_type_id" FOREIGN KEY (tax_rule_type_id) REFERENCES public.tax_rule_type(id);


--
-- Name: tax_rule fk.tax_rule.tax_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_rule
    ADD CONSTRAINT "fk.tax_rule.tax_id" FOREIGN KEY (tax_id) REFERENCES public.tax(id);


--
-- Name: tax_rule_type_translation fk.tax_rule_type_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_rule_type_translation
    ADD CONSTRAINT "fk.tax_rule_type_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: tax_rule_type_translation fk.tax_rule_type_translation.tax_rule_type_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_rule_type_translation
    ADD CONSTRAINT "fk.tax_rule_type_translation.tax_rule_type_id" FOREIGN KEY (tax_rule_type_id) REFERENCES public.tax_rule_type(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: app_template fk.template.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.app_template
    ADD CONSTRAINT "fk.template.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: theme_child fk.theme_child.child_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme_child
    ADD CONSTRAINT "fk.theme_child.child_id" FOREIGN KEY (child_id) REFERENCES public.theme(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: theme_child fk.theme_child.parent_id__theme_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme_child
    ADD CONSTRAINT "fk.theme_child.parent_id__theme_id" FOREIGN KEY (parent_id) REFERENCES public.theme(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: theme_media fk.theme_media.media_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme_media
    ADD CONSTRAINT "fk.theme_media.media_id" FOREIGN KEY (media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: theme_media fk.theme_media.theme_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme_media
    ADD CONSTRAINT "fk.theme_media.theme_id" FOREIGN KEY (theme_id) REFERENCES public.theme(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: theme_sales_channel fk.theme_sales_channel.sales_channel_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme_sales_channel
    ADD CONSTRAINT "fk.theme_sales_channel.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES public.sales_channel(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: theme_sales_channel fk.theme_sales_channel.theme_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme_sales_channel
    ADD CONSTRAINT "fk.theme_sales_channel.theme_id" FOREIGN KEY (theme_id) REFERENCES public.theme(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: theme_translation fk.theme_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme_translation
    ADD CONSTRAINT "fk.theme_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: theme_translation fk.theme_translation.theme_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme_translation
    ADD CONSTRAINT "fk.theme_translation.theme_id" FOREIGN KEY (theme_id) REFERENCES public.theme(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: unit_translation fk.unit_translation.language_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unit_translation
    ADD CONSTRAINT "fk.unit_translation.language_id" FOREIGN KEY (language_id) REFERENCES public.language(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: unit_translation fk.unit_translation.unit_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unit_translation
    ADD CONSTRAINT "fk.unit_translation.unit_id" FOREIGN KEY (unit_id) REFERENCES public.unit(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: user fk.user.avatar_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public."user"
    ADD CONSTRAINT "fk.user.avatar_id" FOREIGN KEY (avatar_id) REFERENCES public.media(id) ON DELETE SET NULL;


--
-- Name: user fk.user.locale_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public."user"
    ADD CONSTRAINT "fk.user.locale_id" FOREIGN KEY (locale_id) REFERENCES public.locale(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: user_access_key fk.user_access_key.user_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_access_key
    ADD CONSTRAINT "fk.user_access_key.user_id" FOREIGN KEY (user_id) REFERENCES public."user"(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: user_config fk.user_config.user_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_config
    ADD CONSTRAINT "fk.user_config.user_id" FOREIGN KEY (user_id) REFERENCES public."user"(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: user_recovery fk.user_recovery.user_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_recovery
    ADD CONSTRAINT "fk.user_recovery.user_id" FOREIGN KEY (user_id) REFERENCES public."user"(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: version_commit_data fk.version_commit_data.version_commit_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_commit_data
    ADD CONSTRAINT "fk.version_commit_data.version_commit_id" FOREIGN KEY (version_commit_id) REFERENCES public.version_commit(id) ON DELETE CASCADE;


--
-- Name: webhook fk.webhook.app_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook
    ADD CONSTRAINT "fk.webhook.app_id" FOREIGN KEY (app_id) REFERENCES public.app(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: webhook_delivery fk.webhook_delivery.webhook_event_log_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_delivery
    ADD CONSTRAINT "fk.webhook_delivery.webhook_event_log_id" FOREIGN KEY (webhook_event_log_id) REFERENCES public.webhook_event_log(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: webhook_delivery fk.webhook_delivery.webhook_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_delivery
    ADD CONSTRAINT "fk.webhook_delivery.webhook_id" FOREIGN KEY (webhook_id) REFERENCES public.webhook(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: product_cross_selling_assigned_products product_cross_selling_assigned_products_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_cross_selling_assigned_products
    ADD CONSTRAINT product_cross_selling_assigned_products_ibfk_2 FOREIGN KEY (product_id, product_version_id) REFERENCES public.product(id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: theme theme_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.theme
    ADD CONSTRAINT theme_ibfk_1 FOREIGN KEY (preview_media_id) REFERENCES public.media(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--
