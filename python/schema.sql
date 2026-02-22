CREATE TABLE IF NOT EXISTS killmails (
    killmail_id       BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    hash              VARCHAR(64)     NOT NULL,
    killmail_time     DATETIME        NOT NULL,
    solar_system_id   INT UNSIGNED    NOT NULL,
    sequence_id       BIGINT UNSIGNED NOT NULL,
    war_id            INT UNSIGNED    NULL,
    moon_id           INT UNSIGNED    NULL,

    -- victim
    victim_character_id   INT UNSIGNED    NULL,
    victim_corporation_id INT UNSIGNED    NULL,
    victim_alliance_id    INT UNSIGNED    NULL,
    victim_faction_id     INT UNSIGNED    NULL,
    victim_ship_type_id   INT UNSIGNED    NOT NULL,
    victim_damage_taken   INT UNSIGNED    NOT NULL,
    victim_pos_x          DOUBLE          NULL,
    victim_pos_y          DOUBLE          NULL,
    victim_pos_z          DOUBLE          NULL,

    -- zkb metadata
    zkb_location_id    BIGINT UNSIGNED NULL,
    zkb_fitted_value   DECIMAL(20,2)   NOT NULL DEFAULT 0,
    zkb_dropped_value  DECIMAL(20,2)   NOT NULL DEFAULT 0,
    zkb_destroyed_value DECIMAL(20,2)  NOT NULL DEFAULT 0,
    zkb_total_value    DECIMAL(20,2)   NOT NULL DEFAULT 0,
    zkb_points         INT             NOT NULL DEFAULT 0,
    zkb_is_npc         TINYINT(1)      NOT NULL DEFAULT 0,
    zkb_is_solo        TINYINT(1)      NOT NULL DEFAULT 0,
    zkb_is_awox        TINYINT(1)      NOT NULL DEFAULT 0,
    zkb_labels         JSON            NULL,
    zkb_href           VARCHAR(255)    NULL,
    zkb_attacker_count INT UNSIGNED    NOT NULL DEFAULT 0,

    uploaded_at        DATETIME        NOT NULL,
    created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_killmail_time (killmail_time),
    INDEX idx_solar_system (solar_system_id),
    INDEX idx_victim_char (victim_character_id),
    INDEX idx_victim_corp (victim_corporation_id),
    INDEX idx_victim_alliance (victim_alliance_id),
    INDEX idx_victim_ship (victim_ship_type_id),
    INDEX idx_sequence (sequence_id),
    INDEX idx_total_value (zkb_total_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS killmail_attackers (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    killmail_id       BIGINT UNSIGNED NOT NULL,
    character_id      INT UNSIGNED    NULL,
    corporation_id    INT UNSIGNED    NULL,
    alliance_id       INT UNSIGNED    NULL,
    faction_id        INT UNSIGNED    NULL,
    ship_type_id      INT UNSIGNED    NULL,
    weapon_type_id    INT UNSIGNED    NULL,
    damage_done       INT UNSIGNED    NOT NULL DEFAULT 0,
    final_blow        TINYINT(1)      NOT NULL DEFAULT 0,
    security_status   DECIMAL(5,2)    NOT NULL DEFAULT 0,

    INDEX idx_attacker_killmail (killmail_id),
    INDEX idx_attacker_char (character_id),
    INDEX idx_attacker_corp (corporation_id),
    INDEX idx_attacker_alliance (alliance_id),
    INDEX idx_attacker_ship (ship_type_id),
    CONSTRAINT fk_attacker_killmail FOREIGN KEY (killmail_id) REFERENCES killmails(killmail_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS killmail_items (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    killmail_id        BIGINT UNSIGNED NOT NULL,
    parent_id          BIGINT UNSIGNED NULL,
    item_type_id       INT UNSIGNED    NOT NULL,
    flag               SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    quantity_destroyed  INT UNSIGNED    NOT NULL DEFAULT 0,
    quantity_dropped    INT UNSIGNED    NOT NULL DEFAULT 0,
    singleton          TINYINT(1)      NOT NULL DEFAULT 0,

    INDEX idx_item_killmail (killmail_id),
    INDEX idx_item_type (item_type_id),
    INDEX idx_item_parent (parent_id),
    CONSTRAINT fk_item_killmail FOREIGN KEY (killmail_id) REFERENCES killmails(killmail_id) ON DELETE CASCADE,
    CONSTRAINT fk_item_parent FOREIGN KEY (parent_id) REFERENCES killmail_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
