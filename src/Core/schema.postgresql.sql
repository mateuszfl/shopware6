-- Bootstrap only. The versioned migration history supplies the complete shop schema.
CREATE TABLE plugin (
    id BYTEA NOT NULL CHECK (octet_length(id) = 16),
    name VARCHAR(255) COLLATE "sw_type_ci" NOT NULL,
    base_class VARCHAR(255) COLLATE "sw_type_ci" NOT NULL,
    composer_name VARCHAR(255) COLLATE "sw_type_ci" NULL,
    active SMALLINT NOT NULL DEFAULT 0,
    managed_by_composer SMALLINT NOT NULL DEFAULT 0,
    path VARCHAR(255) COLLATE "sw_type_ci" NULL,
    autoload JSONB NOT NULL,
    author VARCHAR(255) COLLATE "sw_type_ci" NULL,
    copyright VARCHAR(255) COLLATE "sw_type_ci" NULL,
    license VARCHAR(255) COLLATE "sw_type_ci" NULL,
    version VARCHAR(255) COLLATE "sw_type_ci" NOT NULL,
    upgrade_version VARCHAR(255) COLLATE "sw_type_ci" NULL,
    icon BYTEA NULL,
    installed_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
    upgraded_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
    created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
    PRIMARY KEY (id),
    CONSTRAINT "uniq.name" UNIQUE (name),
    CONSTRAINT "uniq.baseClass" UNIQUE (base_class)
);

CREATE TABLE IF NOT EXISTS migration (
    class VARCHAR(255) COLLATE "sw_type_ci" NOT NULL PRIMARY KEY,
    creation_timestamp INTEGER NOT NULL,
    "update" TIMESTAMP(6) WITHOUT TIME ZONE NULL,
    update_destructive TIMESTAMP(6) WITHOUT TIME ZONE NULL,
    message TEXT COLLATE "sw_type_ci" NULL
);
