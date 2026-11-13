# Database data dictionary

Schema metadata only; no application records, credentials or password hashes are exported.

Generated from the applied PostgreSQL public schema on 2026-11-13 04:08:44 UTC.
41 tables. Regenerate with `php artisan erepair:database-schema --write`.
Use migrations for installation; this document is not a database restore script.

## activity_logs

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `actor_id` | `bigint` | Yes |
| `action` | `character varying(255)` | No |
| `subject_type` | `character varying(255)` | No |
| `subject_id` | `bigint` | Yes |
| `metadata` | `json` | Yes |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `activity_logs_actor_id_foreign`: `FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL`
- `activity_logs_pkey`: `PRIMARY KEY (id)`

### Indexes

- `activity_logs_action_index`: `CREATE INDEX activity_logs_action_index ON public.activity_logs USING btree (action)`
- `activity_logs_pkey`: `CREATE UNIQUE INDEX activity_logs_pkey ON public.activity_logs USING btree (id)`
- `db_activity_actor`: `CREATE INDEX db_activity_actor ON public.activity_logs USING btree (actor_id)`

## appointments

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `repair_request_id` | `bigint` | No |
| `starts_at` | `timestamp(0) with time zone` | No |
| `ends_at` | `timestamp(0) with time zone` | No |
| `service_location` | `text` | No |
| `status` | `character varying(255)` | No |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `appointments_pkey`: `PRIMARY KEY (id)`
- `appointments_repair_request_id_foreign`: `FOREIGN KEY (repair_request_id) REFERENCES repair_requests(id) ON DELETE CASCADE`
- `appointments_status_valid`: `CHECK (((status)::text = ANY ((ARRAY['proposed'::character varying, 'confirmed'::character varying, 'completed'::character varying, 'canceled'::character varying])::text[])))`
- `ordered_appointment`: `CHECK ((ends_at > starts_at))`

### Indexes

- `appointments_pkey`: `CREATE UNIQUE INDEX appointments_pkey ON public.appointments USING btree (id)`
- `appointments_starts_at_index`: `CREATE INDEX appointments_starts_at_index ON public.appointments USING btree (starts_at)`
- `db_appointments_repair`: `CREATE INDEX db_appointments_repair ON public.appointments USING btree (repair_request_id, status, starts_at)`

## cache

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `key` | `character varying(255)` | No |
| `value` | `text` | No |
| `expiration` | `bigint` | No |

### Constraints

- `cache_pkey`: `PRIMARY KEY (key)`

### Indexes

- `cache_expiration_index`: `CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration)`
- `cache_pkey`: `CREATE UNIQUE INDEX cache_pkey ON public.cache USING btree (key)`

## cache_locks

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `key` | `character varying(255)` | No |
| `owner` | `character varying(255)` | No |
| `expiration` | `bigint` | No |

### Constraints

- `cache_locks_pkey`: `PRIMARY KEY (key)`

### Indexes

- `cache_locks_expiration_index`: `CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration)`
- `cache_locks_pkey`: `CREATE UNIQUE INDEX cache_locks_pkey ON public.cache_locks USING btree (key)`

## cms_password_activation_tokens

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `email` | `character varying(255)` | No |
| `token` | `character varying(255)` | No |
| `created_at` | `timestamp(0) without time zone` | Yes |

### Constraints

- `cms_password_activation_tokens_pkey`: `PRIMARY KEY (email)`

### Indexes

- `cms_password_activation_tokens_pkey`: `CREATE UNIQUE INDEX cms_password_activation_tokens_pkey ON public.cms_password_activation_tokens USING btree (email)`

## cms_password_reset_tokens

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `email` | `character varying(255)` | No |
| `token` | `character varying(255)` | No |
| `created_at` | `timestamp(0) without time zone` | Yes |

### Constraints

- `cms_password_reset_tokens_pkey`: `PRIMARY KEY (email)`

### Indexes

- `cms_password_reset_tokens_pkey`: `CREATE UNIQUE INDEX cms_password_reset_tokens_pkey ON public.cms_password_reset_tokens USING btree (email)`

## cms_users

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `user_id` | `bigint` | No |
| `singleton` | `boolean` | No |
| `name` | `character varying(255)` | No |
| `email` | `character varying(255)` | No |
| `password` | `character varying(255)` | No |
| `super` | `boolean` | No |
| `email_verified_at` | `timestamp(0) without time zone` | Yes |
| `avatar` | `character varying(255)` | Yes |
| `preferences` | `json` | Yes |
| `last_login` | `timestamp(0) without time zone` | Yes |
| `two_factor_secret` | `text` | Yes |
| `two_factor_recovery_codes` | `text` | Yes |
| `two_factor_confirmed_at` | `timestamp(0) without time zone` | Yes |
| `remember_token` | `character varying(100)` | Yes |
| `created_at` | `timestamp(0) without time zone` | Yes |
| `updated_at` | `timestamp(0) without time zone` | Yes |

### Constraints

- `cms_users_email_unique`: `UNIQUE (email)`
- `cms_users_pkey`: `PRIMARY KEY (id)`
- `cms_users_single_editor`: `CHECK ((singleton = true))`
- `cms_users_singleton_unique`: `UNIQUE (singleton)`
- `cms_users_user_id_foreign`: `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT`
- `cms_users_user_id_unique`: `UNIQUE (user_id)`

### Indexes

- `cms_users_email_unique`: `CREATE UNIQUE INDEX cms_users_email_unique ON public.cms_users USING btree (email)`
- `cms_users_pkey`: `CREATE UNIQUE INDEX cms_users_pkey ON public.cms_users USING btree (id)`
- `cms_users_singleton_unique`: `CREATE UNIQUE INDEX cms_users_singleton_unique ON public.cms_users USING btree (singleton)`
- `cms_users_user_id_unique`: `CREATE UNIQUE INDEX cms_users_user_id_unique ON public.cms_users USING btree (user_id)`

## device_brands

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `name` | `character varying(255)` | No |
| `slug` | `character varying(255)` | No |
| `description` | `text` | Yes |
| `active` | `boolean` | No |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `device_brands_pkey`: `PRIMARY KEY (id)`
- `device_brands_slug_unique`: `UNIQUE (slug)`

### Indexes

- `device_brands_pkey`: `CREATE UNIQUE INDEX device_brands_pkey ON public.device_brands USING btree (id)`
- `device_brands_slug_unique`: `CREATE UNIQUE INDEX device_brands_slug_unique ON public.device_brands USING btree (slug)`

## device_types

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `name` | `character varying(255)` | No |
| `slug` | `character varying(255)` | No |
| `description` | `text` | Yes |
| `active` | `boolean` | No |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `device_types_pkey`: `PRIMARY KEY (id)`
- `device_types_slug_unique`: `UNIQUE (slug)`

### Indexes

- `device_types_pkey`: `CREATE UNIQUE INDEX device_types_pkey ON public.device_types USING btree (id)`
- `device_types_slug_unique`: `CREATE UNIQUE INDEX device_types_slug_unique ON public.device_types USING btree (slug)`

## failed_jobs

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `uuid` | `character varying(255)` | No |
| `connection` | `character varying(255)` | No |
| `queue` | `character varying(255)` | No |
| `payload` | `text` | No |
| `exception` | `text` | No |
| `failed_at` | `timestamp(0) without time zone` | No |

### Constraints

- `failed_jobs_pkey`: `PRIMARY KEY (id)`
- `failed_jobs_uuid_unique`: `UNIQUE (uuid)`

### Indexes

- `failed_jobs_connection_queue_failed_at_index`: `CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at)`
- `failed_jobs_pkey`: `CREATE UNIQUE INDEX failed_jobs_pkey ON public.failed_jobs USING btree (id)`
- `failed_jobs_uuid_unique`: `CREATE UNIQUE INDEX failed_jobs_uuid_unique ON public.failed_jobs USING btree (uuid)`

## group_user

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `user_id` | `bigint` | No |
| `group_id` | `character varying(255)` | No |

### Constraints

- `group_user_pkey`: `PRIMARY KEY (id)`
- `group_user_user_id_foreign`: `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`
- `group_user_user_id_group_id_unique`: `UNIQUE (user_id, group_id)`

### Indexes

- `group_user_pkey`: `CREATE UNIQUE INDEX group_user_pkey ON public.group_user USING btree (id)`
- `group_user_user_id_group_id_unique`: `CREATE UNIQUE INDEX group_user_user_id_group_id_unique ON public.group_user USING btree (user_id, group_id)`

## hosted_file_states

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `smallint` | No |
| `epoch` | `uuid` | No |
| `revision` | `bigint` | No |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `hosted_file_states_pkey`: `PRIMARY KEY (id)`

### Indexes

- `hosted_file_states_pkey`: `CREATE UNIQUE INDEX hosted_file_states_pkey ON public.hosted_file_states USING btree (id)`

## hosted_files

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `root` | `character varying(32)` | No |
| `path` | `text` | No |
| `path_hash` | `character(64)` | No |
| `is_directory` | `boolean` | No |
| `size` | `bigint` | No |
| `checksum` | `character(64)` | Yes |
| `payload` | `text` | Yes |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `hosted_files_pkey`: `PRIMARY KEY (id)`
- `hosted_files_root_path_hash_unique`: `UNIQUE (root, path_hash)`

### Indexes

- `hosted_files_pkey`: `CREATE UNIQUE INDEX hosted_files_pkey ON public.hosted_files USING btree (id)`
- `hosted_files_root_path_hash_unique`: `CREATE UNIQUE INDEX hosted_files_root_path_hash_unique ON public.hosted_files USING btree (root, path_hash)`

## inventory_movements

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `technician_id` | `bigint` | No |
| `inventory_part_id` | `bigint` | No |
| `repair_request_id` | `bigint` | Yes |
| `actor_id` | `bigint` | No |
| `kind` | `character varying(16)` | No |
| `quantity` | `integer` | No |
| `unit_cost` | `numeric(12,2)` | No |
| `note` | `character varying(500)` | Yes |
| `idempotency_key` | `uuid` | No |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `inventory_movement_part_owner_fk`: `FOREIGN KEY (inventory_part_id, technician_id) REFERENCES inventory_parts(id, technician_id) ON DELETE RESTRICT`
- `inventory_movements_actor_id_foreign`: `FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE RESTRICT`
- `inventory_movements_pkey`: `PRIMARY KEY (id)`
- `inventory_movements_repair_request_id_foreign`: `FOREIGN KEY (repair_request_id) REFERENCES repair_requests(id) ON DELETE RESTRICT`
- `inventory_movements_technician_id_foreign`: `FOREIGN KEY (technician_id) REFERENCES technician_profiles(id) ON DELETE RESTRICT`
- `inventory_movements_technician_id_idempotency_key_unique`: `UNIQUE (technician_id, idempotency_key)`
- `inventory_movements_valid`: `CHECK (((quantity > 0) AND (unit_cost >= (0)::numeric) AND ((((kind)::text = 'usage'::text) AND (repair_request_id IS NOT NULL)) OR (((kind)::text = ANY ((ARRAY['opening'::character varying, 'restock'::character varying])::text[])) AND (repair_request_id IS NULL)))))`

### Indexes

- `inventory_movements_actor_id_index`: `CREATE INDEX inventory_movements_actor_id_index ON public.inventory_movements USING btree (actor_id)`
- `inventory_movements_inventory_part_id_index`: `CREATE INDEX inventory_movements_inventory_part_id_index ON public.inventory_movements USING btree (inventory_part_id)`
- `inventory_movements_pkey`: `CREATE UNIQUE INDEX inventory_movements_pkey ON public.inventory_movements USING btree (id)`
- `inventory_movements_repair_request_id_index`: `CREATE INDEX inventory_movements_repair_request_id_index ON public.inventory_movements USING btree (repair_request_id)`
- `inventory_movements_technician_id_created_at_index`: `CREATE INDEX inventory_movements_technician_id_created_at_index ON public.inventory_movements USING btree (technician_id, created_at)`
- `inventory_movements_technician_id_idempotency_key_unique`: `CREATE UNIQUE INDEX inventory_movements_technician_id_idempotency_key_unique ON public.inventory_movements USING btree (technician_id, idempotency_key)`

## inventory_parts

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `technician_id` | `bigint` | No |
| `sku` | `character varying(64)` | No |
| `name` | `character varying(120)` | No |
| `quantity` | `integer` | No |
| `reorder_level` | `integer` | No |
| `unit_cost` | `numeric(12,2)` | No |
| `active` | `boolean` | No |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `inventory_part_owner_key`: `UNIQUE (id, technician_id)`
- `inventory_parts_nonnegative`: `CHECK (((quantity >= 0) AND (reorder_level >= 0) AND (unit_cost >= (0)::numeric)))`
- `inventory_parts_pkey`: `PRIMARY KEY (id)`
- `inventory_parts_technician_id_foreign`: `FOREIGN KEY (technician_id) REFERENCES technician_profiles(id) ON DELETE RESTRICT`
- `inventory_parts_technician_id_sku_unique`: `UNIQUE (technician_id, sku)`

### Indexes

- `inventory_part_owner_key`: `CREATE UNIQUE INDEX inventory_part_owner_key ON public.inventory_parts USING btree (id, technician_id)`
- `inventory_parts_pkey`: `CREATE UNIQUE INDEX inventory_parts_pkey ON public.inventory_parts USING btree (id)`
- `inventory_parts_technician_id_sku_unique`: `CREATE UNIQUE INDEX inventory_parts_technician_id_sku_unique ON public.inventory_parts USING btree (technician_id, sku)`

## job_batches

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `character varying(255)` | No |
| `name` | `character varying(255)` | No |
| `total_jobs` | `integer` | No |
| `pending_jobs` | `integer` | No |
| `failed_jobs` | `integer` | No |
| `failed_job_ids` | `text` | No |
| `options` | `text` | Yes |
| `cancelled_at` | `integer` | Yes |
| `created_at` | `integer` | No |
| `finished_at` | `integer` | Yes |

### Constraints

- `job_batches_pkey`: `PRIMARY KEY (id)`

### Indexes

- `job_batches_pkey`: `CREATE UNIQUE INDEX job_batches_pkey ON public.job_batches USING btree (id)`

## jobs

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `queue` | `character varying(255)` | No |
| `payload` | `text` | No |
| `attempts` | `smallint` | No |
| `reserved_at` | `integer` | Yes |
| `available_at` | `integer` | No |
| `created_at` | `integer` | No |

### Constraints

- `jobs_pkey`: `PRIMARY KEY (id)`

### Indexes

- `jobs_pkey`: `CREATE UNIQUE INDEX jobs_pkey ON public.jobs USING btree (id)`
- `jobs_queue_index`: `CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue)`

## migrations

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `integer` | No |
| `migration` | `character varying(255)` | No |
| `batch` | `integer` | No |

### Constraints

- `migrations_pkey`: `PRIMARY KEY (id)`

### Indexes

- `migrations_pkey`: `CREATE UNIQUE INDEX migrations_pkey ON public.migrations USING btree (id)`

## note_templates

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `technician_id` | `bigint` | No |
| `title` | `character varying(100)` | No |
| `body` | `text` | No |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `note_templates_pkey`: `PRIMARY KEY (id)`
- `note_templates_technician_id_foreign`: `FOREIGN KEY (technician_id) REFERENCES technician_profiles(id) ON DELETE RESTRICT`
- `note_templates_technician_id_title_unique`: `UNIQUE (technician_id, title)`

### Indexes

- `note_templates_pkey`: `CREATE UNIQUE INDEX note_templates_pkey ON public.note_templates USING btree (id)`
- `note_templates_technician_id_title_unique`: `CREATE UNIQUE INDEX note_templates_technician_id_title_unique ON public.note_templates USING btree (technician_id, title)`

## notifications

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `uuid` | No |
| `type` | `character varying(255)` | No |
| `notifiable_type` | `character varying(255)` | No |
| `notifiable_id` | `bigint` | No |
| `data` | `text` | No |
| `read_at` | `timestamp(0) with time zone` | Yes |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `notifications_pkey`: `PRIMARY KEY (id)`

### Indexes

- `notifications_notifiable_type_notifiable_id_index`: `CREATE INDEX notifications_notifiable_type_notifiable_id_index ON public.notifications USING btree (notifiable_type, notifiable_id)`
- `notifications_pkey`: `CREATE UNIQUE INDEX notifications_pkey ON public.notifications USING btree (id)`

## password_activation_tokens

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `email` | `character varying(255)` | No |
| `token` | `character varying(255)` | No |
| `created_at` | `timestamp(0) without time zone` | Yes |

### Constraints

- `password_activation_tokens_pkey`: `PRIMARY KEY (email)`

### Indexes

- `password_activation_tokens_pkey`: `CREATE UNIQUE INDEX password_activation_tokens_pkey ON public.password_activation_tokens USING btree (email)`

## password_reset_tokens

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `email` | `character varying(255)` | No |
| `token` | `character varying(255)` | No |
| `created_at` | `timestamp(0) without time zone` | Yes |

### Constraints

- `password_reset_tokens_pkey`: `PRIMARY KEY (email)`

### Indexes

- `password_reset_tokens_pkey`: `CREATE UNIQUE INDEX password_reset_tokens_pkey ON public.password_reset_tokens USING btree (email)`

## repair_attachments

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `repair_request_id` | `bigint` | No |
| `uploaded_by` | `bigint` | No |
| `path` | `character varying(255)` | No |
| `original_name` | `character varying(255)` | No |
| `mime` | `character varying(255)` | No |
| `size` | `bigint` | No |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `repair_attachments_pkey`: `PRIMARY KEY (id)`
- `repair_attachments_repair_request_id_foreign`: `FOREIGN KEY (repair_request_id) REFERENCES repair_requests(id) ON DELETE CASCADE`
- `repair_attachments_uploaded_by_foreign`: `FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT`

### Indexes

- `db_attachments_repair`: `CREATE INDEX db_attachments_repair ON public.repair_attachments USING btree (repair_request_id, id)`
- `db_attachments_uploader`: `CREATE INDEX db_attachments_uploader ON public.repair_attachments USING btree (uploaded_by)`
- `repair_attachments_pkey`: `CREATE UNIQUE INDEX repair_attachments_pkey ON public.repair_attachments USING btree (id)`

## repair_categories

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `name` | `character varying(255)` | No |
| `slug` | `character varying(255)` | No |
| `device_type_id` | `bigint` | Yes |
| `specialization_id` | `bigint` | Yes |
| `active` | `boolean` | No |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `repair_categories_device_type_id_foreign`: `FOREIGN KEY (device_type_id) REFERENCES device_types(id) ON DELETE RESTRICT`
- `repair_categories_pkey`: `PRIMARY KEY (id)`
- `repair_categories_slug_unique`: `UNIQUE (slug)`
- `repair_categories_specialization_id_foreign`: `FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE RESTRICT`

### Indexes

- `db_categories_device`: `CREATE INDEX db_categories_device ON public.repair_categories USING btree (device_type_id)`
- `db_categories_specialization`: `CREATE INDEX db_categories_specialization ON public.repair_categories USING btree (specialization_id)`
- `repair_categories_pkey`: `CREATE UNIQUE INDEX repair_categories_pkey ON public.repair_categories USING btree (id)`
- `repair_categories_slug_unique`: `CREATE UNIQUE INDEX repair_categories_slug_unique ON public.repair_categories USING btree (slug)`

## repair_estimates

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `repair_request_id` | `bigint` | No |
| `amount` | `numeric(12,2)` | No |
| `currency` | `character varying(3)` | No |
| `description` | `text` | No |
| `status` | `character varying(255)` | No |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `positive_estimate`: `CHECK ((amount > (0)::numeric))`
- `repair_estimates_pkey`: `PRIMARY KEY (id)`
- `repair_estimates_repair_request_id_foreign`: `FOREIGN KEY (repair_request_id) REFERENCES repair_requests(id) ON DELETE CASCADE`
- `repair_estimates_state_valid`: `CHECK ((((status)::text = ANY ((ARRAY['pending'::character varying, 'approved'::character varying, 'rejected'::character varying, 'superseded'::character varying])::text[])) AND ((currency)::text = 'PHP'::text)))`

### Indexes

- `db_estimates_repair`: `CREATE INDEX db_estimates_repair ON public.repair_estimates USING btree (repair_request_id, id)`
- `repair_estimates_pkey`: `CREATE UNIQUE INDEX repair_estimates_pkey ON public.repair_estimates USING btree (id)`

## repair_requests

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `reference` | `character varying(255)` | No |
| `customer_id` | `bigint` | No |
| `technician_profile_id` | `bigint` | No |
| `device_type_id` | `bigint` | No |
| `device_brand_id` | `bigint` | No |
| `repair_category_id` | `bigint` | No |
| `model` | `character varying(255)` | No |
| `serial_number` | `text` | Yes |
| `problem_description` | `text` | No |
| `status` | `character varying(255)` | No |
| `completed_at` | `timestamp(0) with time zone` | Yes |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `db_repair_owners_key`: `UNIQUE (id, customer_id, technician_profile_id)`
- `repair_requests_customer_id_foreign`: `FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE RESTRICT`
- `repair_requests_device_brand_id_foreign`: `FOREIGN KEY (device_brand_id) REFERENCES device_brands(id) ON DELETE RESTRICT`
- `repair_requests_device_type_id_foreign`: `FOREIGN KEY (device_type_id) REFERENCES device_types(id) ON DELETE RESTRICT`
- `repair_requests_pkey`: `PRIMARY KEY (id)`
- `repair_requests_reference_unique`: `UNIQUE (reference)`
- `repair_requests_repair_category_id_foreign`: `FOREIGN KEY (repair_category_id) REFERENCES repair_categories(id) ON DELETE RESTRICT`
- `repair_requests_technician_profile_id_foreign`: `FOREIGN KEY (technician_profile_id) REFERENCES technician_profiles(id) ON DELETE RESTRICT`
- `repair_status_values`: `CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'accepted'::character varying, 'scheduled'::character varying, 'diagnosing'::character varying, 'awaiting_customer_approval'::character varying, 'in_repair'::character varying, 'ready_for_pickup'::character varying, 'completed'::character varying, 'canceled'::character varying, 'rejected'::character varying])::text[])))`

### Indexes

- `db_repair_owners_key`: `CREATE UNIQUE INDEX db_repair_owners_key ON public.repair_requests USING btree (id, customer_id, technician_profile_id)`
- `db_requests_brand`: `CREATE INDEX db_requests_brand ON public.repair_requests USING btree (device_brand_id)`
- `db_requests_category`: `CREATE INDEX db_requests_category ON public.repair_requests USING btree (repair_category_id)`
- `db_requests_device`: `CREATE INDEX db_requests_device ON public.repair_requests USING btree (device_type_id)`
- `repair_requests_customer_id_status_created_at_index`: `CREATE INDEX repair_requests_customer_id_status_created_at_index ON public.repair_requests USING btree (customer_id, status, created_at)`
- `repair_requests_pkey`: `CREATE UNIQUE INDEX repair_requests_pkey ON public.repair_requests USING btree (id)`
- `repair_requests_reference_unique`: `CREATE UNIQUE INDEX repair_requests_reference_unique ON public.repair_requests USING btree (reference)`
- `repair_requests_technician_profile_id_status_index`: `CREATE INDEX repair_requests_technician_profile_id_status_index ON public.repair_requests USING btree (technician_profile_id, status)`

## repair_status_histories

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `repair_request_id` | `bigint` | No |
| `previous_status` | `character varying(255)` | Yes |
| `new_status` | `character varying(255)` | No |
| `updated_by` | `bigint` | No |
| `remarks` | `text` | Yes |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `repair_status_histories_pkey`: `PRIMARY KEY (id)`
- `repair_status_histories_repair_request_id_foreign`: `FOREIGN KEY (repair_request_id) REFERENCES repair_requests(id) ON DELETE CASCADE`
- `repair_status_histories_updated_by_foreign`: `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT`

### Indexes

- `db_history_actor`: `CREATE INDEX db_history_actor ON public.repair_status_histories USING btree (updated_by)`
- `db_history_repair`: `CREATE INDEX db_history_repair ON public.repair_status_histories USING btree (repair_request_id, id)`
- `repair_status_histories_pkey`: `CREATE UNIQUE INDEX repair_status_histories_pkey ON public.repair_status_histories USING btree (id)`

## reviews

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `repair_request_id` | `bigint` | No |
| `customer_id` | `bigint` | No |
| `technician_profile_id` | `bigint` | No |
| `rating` | `smallint` | No |
| `body` | `text` | No |
| `hidden` | `boolean` | No |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `review_repair_owners_fk`: `FOREIGN KEY (repair_request_id, customer_id, technician_profile_id) REFERENCES repair_requests(id, customer_id, technician_profile_id) ON DELETE RESTRICT`
- `reviews_customer_id_foreign`: `FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE RESTRICT`
- `reviews_pkey`: `PRIMARY KEY (id)`
- `reviews_rating_range`: `CHECK (((rating >= 1) AND (rating <= 5)))`
- `reviews_repair_request_id_foreign`: `FOREIGN KEY (repair_request_id) REFERENCES repair_requests(id) ON DELETE RESTRICT`
- `reviews_repair_request_id_unique`: `UNIQUE (repair_request_id)`
- `reviews_technician_profile_id_foreign`: `FOREIGN KEY (technician_profile_id) REFERENCES technician_profiles(id) ON DELETE RESTRICT`

### Indexes

- `db_reviews_customer`: `CREATE INDEX db_reviews_customer ON public.reviews USING btree (customer_id)`
- `db_reviews_technician`: `CREATE INDEX db_reviews_technician ON public.reviews USING btree (technician_profile_id, hidden)`
- `reviews_pkey`: `CREATE UNIQUE INDEX reviews_pkey ON public.reviews USING btree (id)`
- `reviews_repair_request_id_unique`: `CREATE UNIQUE INDEX reviews_repair_request_id_unique ON public.reviews USING btree (repair_request_id)`

## role_user

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `user_id` | `bigint` | No |
| `role_id` | `character varying(255)` | No |

### Constraints

- `role_user_pkey`: `PRIMARY KEY (id)`
- `role_user_user_id_foreign`: `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`
- `role_user_user_id_role_id_unique`: `UNIQUE (user_id, role_id)`

### Indexes

- `role_user_pkey`: `CREATE UNIQUE INDEX role_user_pkey ON public.role_user USING btree (id)`
- `role_user_user_id_role_id_unique`: `CREATE UNIQUE INDEX role_user_user_id_role_id_unique ON public.role_user USING btree (user_id, role_id)`

## sessions

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `character varying(255)` | No |
| `user_id` | `bigint` | Yes |
| `ip_address` | `character varying(45)` | Yes |
| `user_agent` | `text` | Yes |
| `payload` | `text` | No |
| `last_activity` | `integer` | No |

### Constraints

- `sessions_pkey`: `PRIMARY KEY (id)`

### Indexes

- `sessions_last_activity_index`: `CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity)`
- `sessions_pkey`: `CREATE UNIQUE INDEX sessions_pkey ON public.sessions USING btree (id)`
- `sessions_user_id_index`: `CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id)`

## specializations

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `name` | `character varying(255)` | No |
| `slug` | `character varying(255)` | No |
| `description` | `text` | Yes |
| `active` | `boolean` | No |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `specializations_pkey`: `PRIMARY KEY (id)`
- `specializations_slug_unique`: `UNIQUE (slug)`

### Indexes

- `specializations_pkey`: `CREATE UNIQUE INDEX specializations_pkey ON public.specializations USING btree (id)`
- `specializations_slug_unique`: `CREATE UNIQUE INDEX specializations_slug_unique ON public.specializations USING btree (slug)`

## subscription_demo_payments

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `technician_id` | `bigint` | No |
| `technician_subscription_id` | `bigint` | No |
| `amount` | `numeric(10,2)` | No |
| `duration_days` | `integer` | No |
| `plan_name` | `character varying(100)` | No |
| `transaction_reference` | `character varying(64)` | No |
| `idempotency_key` | `uuid` | No |
| `payment_method` | `character varying(30)` | No |
| `status` | `character varying(20)` | No |
| `is_demo` | `boolean` | No |
| `is_seeded_demo` | `boolean` | No |
| `approved_by` | `bigint` | Yes |
| `approved_at` | `timestamp(0) with time zone` | Yes |
| `rejected_at` | `timestamp(0) with time zone` | Yes |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `demo_payment_valid`: `CHECK (((is_demo = true) AND ((payment_method)::text = 'demo_simulation'::text) AND (amount >= (0)::numeric) AND ((duration_days >= 1) AND (duration_days <= 365)) AND ((status)::text = ANY ((ARRAY['pending'::character varying, 'approved'::character varying, 'rejected'::character varying])::text[])) AND ((((status)::text = 'pending'::text) AND (approved_at IS NULL) AND (approved_by IS NULL) AND (rejected_at IS NULL)) OR (((status)::text = 'approved'::text) AND (approved_at IS NOT NULL) AND (approved_by IS NOT NULL) AND (rejected_at IS NULL)) OR (((status)::text = 'rejected'::text) AND (rejected_at IS NOT NULL) AND (approved_at IS NULL) AND (approved_by IS NULL)))))`
- `payment_subscription_owner_fk`: `FOREIGN KEY (technician_subscription_id, technician_id) REFERENCES technician_subscriptions(id, technician_id) ON DELETE CASCADE`
- `subscription_demo_payments_approved_by_foreign`: `FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE RESTRICT`
- `subscription_demo_payments_pkey`: `PRIMARY KEY (id)`
- `subscription_demo_payments_technician_id_foreign`: `FOREIGN KEY (technician_id) REFERENCES technician_profiles(id) ON DELETE RESTRICT`
- `subscription_demo_payments_technician_id_idempotency_key_unique`: `UNIQUE (technician_id, idempotency_key)`
- `subscription_demo_payments_technician_subscription_id_foreign`: `FOREIGN KEY (technician_subscription_id) REFERENCES technician_subscriptions(id) ON DELETE CASCADE`
- `subscription_demo_payments_technician_subscription_id_unique`: `UNIQUE (technician_subscription_id)`
- `subscription_demo_payments_transaction_reference_unique`: `UNIQUE (transaction_reference)`

### Indexes

- `db_payments_approver`: `CREATE INDEX db_payments_approver ON public.subscription_demo_payments USING btree (approved_by)`
- `demo_payment_one_pending`: `CREATE UNIQUE INDEX demo_payment_one_pending ON public.subscription_demo_payments USING btree (technician_id) WHERE ((status)::text = 'pending'::text)`
- `subscription_demo_payments_approved_at_index`: `CREATE INDEX subscription_demo_payments_approved_at_index ON public.subscription_demo_payments USING btree (approved_at)`
- `subscription_demo_payments_pkey`: `CREATE UNIQUE INDEX subscription_demo_payments_pkey ON public.subscription_demo_payments USING btree (id)`
- `subscription_demo_payments_status_index`: `CREATE INDEX subscription_demo_payments_status_index ON public.subscription_demo_payments USING btree (status)`
- `subscription_demo_payments_technician_id_idempotency_key_unique`: `CREATE UNIQUE INDEX subscription_demo_payments_technician_id_idempotency_key_unique ON public.subscription_demo_payments USING btree (technician_id, idempotency_key)`
- `subscription_demo_payments_technician_subscription_id_unique`: `CREATE UNIQUE INDEX subscription_demo_payments_technician_subscription_id_unique ON public.subscription_demo_payments USING btree (technician_subscription_id)`
- `subscription_demo_payments_transaction_reference_unique`: `CREATE UNIQUE INDEX subscription_demo_payments_transaction_reference_unique ON public.subscription_demo_payments USING btree (transaction_reference)`

## subscription_plans

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `name` | `character varying(100)` | No |
| `slug` | `character varying(255)` | No |
| `description` | `text` | No |
| `price` | `numeric(10,2)` | No |
| `duration_days` | `integer` | No |
| `features` | `json` | No |
| `is_active` | `boolean` | No |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `subscription_plan_valid`: `CHECK (((price >= (0)::numeric) AND ((duration_days >= 1) AND (duration_days <= 365)) AND (((slug)::text <> 'free'::text) OR (price = (0)::numeric))))`
- `subscription_plans_pkey`: `PRIMARY KEY (id)`
- `subscription_plans_slug_unique`: `UNIQUE (slug)`

### Indexes

- `subscription_plans_pkey`: `CREATE UNIQUE INDEX subscription_plans_pkey ON public.subscription_plans USING btree (id)`
- `subscription_plans_slug_unique`: `CREATE UNIQUE INDEX subscription_plans_slug_unique ON public.subscription_plans USING btree (slug)`

## technician_device_types

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `technician_profile_id` | `bigint` | No |
| `device_type_id` | `bigint` | No |

### Constraints

- `technician_device_types_device_type_id_foreign`: `FOREIGN KEY (device_type_id) REFERENCES device_types(id) ON DELETE RESTRICT`
- `technician_device_types_pkey`: `PRIMARY KEY (id)`
- `technician_device_types_technician_profile_id_device_type_id_un`: `UNIQUE (technician_profile_id, device_type_id)`
- `technician_device_types_technician_profile_id_foreign`: `FOREIGN KEY (technician_profile_id) REFERENCES technician_profiles(id) ON DELETE CASCADE`

### Indexes

- `db_devices_reverse`: `CREATE INDEX db_devices_reverse ON public.technician_device_types USING btree (device_type_id, technician_profile_id)`
- `technician_device_types_pkey`: `CREATE UNIQUE INDEX technician_device_types_pkey ON public.technician_device_types USING btree (id)`
- `technician_device_types_technician_profile_id_device_type_id_un`: `CREATE UNIQUE INDEX technician_device_types_technician_profile_id_device_type_id_un ON public.technician_device_types USING btree (technician_profile_id, device_type_id)`

## technician_profile_views

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `technician_id` | `bigint` | No |
| `viewed_on` | `date` | No |
| `visitor_hash` | `character(64)` | No |

### Constraints

- `technician_profile_views_pkey`: `PRIMARY KEY (id)`
- `technician_profile_views_technician_id_foreign`: `FOREIGN KEY (technician_id) REFERENCES technician_profiles(id) ON DELETE CASCADE`
- `technician_profile_views_technician_id_viewed_on_visitor_hash_u`: `UNIQUE (technician_id, viewed_on, visitor_hash)`

### Indexes

- `technician_profile_views_pkey`: `CREATE UNIQUE INDEX technician_profile_views_pkey ON public.technician_profile_views USING btree (id)`
- `technician_profile_views_technician_id_viewed_on_index`: `CREATE INDEX technician_profile_views_technician_id_viewed_on_index ON public.technician_profile_views USING btree (technician_id, viewed_on)`
- `technician_profile_views_technician_id_viewed_on_visitor_hash_u`: `CREATE UNIQUE INDEX technician_profile_views_technician_id_viewed_on_visitor_hash_u ON public.technician_profile_views USING btree (technician_id, viewed_on, visitor_hash)`

## technician_profiles

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `user_id` | `bigint` | No |
| `biography` | `text` | Yes |
| `years_experience` | `smallint` | No |
| `city` | `character varying(255)` | No |
| `province` | `character varying(255)` | No |
| `latitude` | `numeric(8,5)` | Yes |
| `longitude` | `numeric(8,5)` | Yes |
| `photo_path` | `character varying(255)` | Yes |
| `availability` | `character varying(255)` | No |
| `verification_status` | `character varying(255)` | No |
| `verified_by` | `bigint` | Yes |
| `verification_notes` | `text` | Yes |
| `verified_at` | `timestamp(0) with time zone` | Yes |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `technician_availability_values`: `CHECK (((availability)::text = ANY ((ARRAY['available'::character varying, 'busy'::character varying, 'unavailable'::character varying])::text[])))`
- `technician_experience_valid`: `CHECK (((years_experience >= 0) AND (years_experience <= 60)))`
- `technician_profiles_pkey`: `PRIMARY KEY (id)`
- `technician_profiles_user_id_foreign`: `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT`
- `technician_profiles_user_id_unique`: `UNIQUE (user_id)`
- `technician_profiles_verified_by_foreign`: `FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL`
- `technician_verification_values`: `CHECK (((verification_status)::text = ANY ((ARRAY['pending'::character varying, 'approved'::character varying, 'rejected'::character varying])::text[])))`

### Indexes

- `db_profiles_verifier`: `CREATE INDEX db_profiles_verifier ON public.technician_profiles USING btree (verified_by)`
- `technician_profiles_availability_index`: `CREATE INDEX technician_profiles_availability_index ON public.technician_profiles USING btree (availability)`
- `technician_profiles_city_index`: `CREATE INDEX technician_profiles_city_index ON public.technician_profiles USING btree (city)`
- `technician_profiles_pkey`: `CREATE UNIQUE INDEX technician_profiles_pkey ON public.technician_profiles USING btree (id)`
- `technician_profiles_province_index`: `CREATE INDEX technician_profiles_province_index ON public.technician_profiles USING btree (province)`
- `technician_profiles_user_id_unique`: `CREATE UNIQUE INDEX technician_profiles_user_id_unique ON public.technician_profiles USING btree (user_id)`
- `technician_profiles_verification_status_index`: `CREATE INDEX technician_profiles_verification_status_index ON public.technician_profiles USING btree (verification_status)`

## technician_service_areas

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `technician_profile_id` | `bigint` | No |
| `city` | `character varying(255)` | No |
| `province` | `character varying(255)` | No |

### Constraints

- `technician_service_areas_pkey`: `PRIMARY KEY (id)`
- `technician_service_areas_technician_profile_id_city_province_un`: `UNIQUE (technician_profile_id, city, province)`
- `technician_service_areas_technician_profile_id_foreign`: `FOREIGN KEY (technician_profile_id) REFERENCES technician_profiles(id) ON DELETE CASCADE`

### Indexes

- `technician_service_areas_pkey`: `CREATE UNIQUE INDEX technician_service_areas_pkey ON public.technician_service_areas USING btree (id)`
- `technician_service_areas_technician_profile_id_city_province_un`: `CREATE UNIQUE INDEX technician_service_areas_technician_profile_id_city_province_un ON public.technician_service_areas USING btree (technician_profile_id, city, province)`

## technician_specializations

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `technician_profile_id` | `bigint` | No |
| `specialization_id` | `bigint` | No |

### Constraints

- `technician_specializations_pkey`: `PRIMARY KEY (id)`
- `technician_specializations_specialization_id_foreign`: `FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE RESTRICT`
- `technician_specializations_technician_profile_id_foreign`: `FOREIGN KEY (technician_profile_id) REFERENCES technician_profiles(id) ON DELETE CASCADE`
- `technician_specializations_technician_profile_id_specialization`: `UNIQUE (technician_profile_id, specialization_id)`

### Indexes

- `db_specializations_reverse`: `CREATE INDEX db_specializations_reverse ON public.technician_specializations USING btree (specialization_id, technician_profile_id)`
- `technician_specializations_pkey`: `CREATE UNIQUE INDEX technician_specializations_pkey ON public.technician_specializations USING btree (id)`
- `technician_specializations_technician_profile_id_specialization`: `CREATE UNIQUE INDEX technician_specializations_technician_profile_id_specialization ON public.technician_specializations USING btree (technician_profile_id, specialization_id)`

## technician_subscriptions

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `technician_id` | `bigint` | No |
| `subscription_plan_id` | `bigint` | No |
| `status` | `character varying(20)` | No |
| `starts_at` | `timestamp(0) with time zone` | Yes |
| `expires_at` | `timestamp(0) with time zone` | Yes |
| `created_at` | `timestamp(0) with time zone` | Yes |
| `updated_at` | `timestamp(0) with time zone` | Yes |

### Constraints

- `db_subscription_owner_key`: `UNIQUE (id, technician_id)`
- `subscription_valid`: `CHECK ((((status)::text = ANY ((ARRAY['pending'::character varying, 'active'::character varying, 'scheduled'::character varying, 'expired'::character varying, 'cancelled'::character varying])::text[])) AND (((status)::text <> ALL ((ARRAY['active'::character varying, 'scheduled'::character varying])::text[])) OR ((starts_at IS NOT NULL) AND (expires_at IS NOT NULL) AND (expires_at > starts_at)))))`
- `technician_subscriptions_pkey`: `PRIMARY KEY (id)`
- `technician_subscriptions_subscription_plan_id_foreign`: `FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(id) ON DELETE RESTRICT`
- `technician_subscriptions_technician_id_foreign`: `FOREIGN KEY (technician_id) REFERENCES technician_profiles(id) ON DELETE RESTRICT`

### Indexes

- `db_subscription_owner_key`: `CREATE UNIQUE INDEX db_subscription_owner_key ON public.technician_subscriptions USING btree (id, technician_id)`
- `db_subscriptions_plan`: `CREATE INDEX db_subscriptions_plan ON public.technician_subscriptions USING btree (subscription_plan_id)`
- `subscription_one_active`: `CREATE UNIQUE INDEX subscription_one_active ON public.technician_subscriptions USING btree (technician_id) WHERE ((status)::text = 'active'::text)`
- `subscription_one_pending`: `CREATE UNIQUE INDEX subscription_one_pending ON public.technician_subscriptions USING btree (technician_id) WHERE ((status)::text = 'pending'::text)`
- `subscription_one_scheduled`: `CREATE UNIQUE INDEX subscription_one_scheduled ON public.technician_subscriptions USING btree (technician_id) WHERE ((status)::text = 'scheduled'::text)`
- `technician_subscriptions_expires_at_index`: `CREATE INDEX technician_subscriptions_expires_at_index ON public.technician_subscriptions USING btree (expires_at)`
- `technician_subscriptions_pkey`: `CREATE UNIQUE INDEX technician_subscriptions_pkey ON public.technician_subscriptions USING btree (id)`
- `technician_subscriptions_technician_id_status_index`: `CREATE INDEX technician_subscriptions_technician_id_status_index ON public.technician_subscriptions USING btree (technician_id, status)`

## users

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `bigint` | No |
| `name` | `character varying(255)` | No |
| `email` | `character varying(255)` | No |
| `email_verified_at` | `timestamp(0) without time zone` | Yes |
| `password` | `character varying(255)` | No |
| `remember_token` | `character varying(100)` | Yes |
| `created_at` | `timestamp(0) without time zone` | Yes |
| `updated_at` | `timestamp(0) without time zone` | Yes |
| `role` | `character varying(255)` | No |
| `account_status` | `character varying(255)` | No |
| `super` | `boolean` | No |
| `avatar` | `character varying(255)` | Yes |
| `preferences` | `json` | Yes |
| `last_login` | `timestamp(0) without time zone` | Yes |
| `two_factor_secret` | `text` | Yes |
| `two_factor_recovery_codes` | `text` | Yes |
| `two_factor_confirmed_at` | `timestamp(0) without time zone` | Yes |

### Constraints

- `users_account_status_valid`: `CHECK (((account_status)::text = ANY ((ARRAY['active'::character varying, 'suspended'::character varying])::text[])))`
- `users_business_role`: `CHECK (((role)::text = ANY ((ARRAY['customer'::character varying, 'technician'::character varying, 'admin'::character varying])::text[])))`
- `users_email_unique`: `UNIQUE (email)`
- `users_pkey`: `PRIMARY KEY (id)`

### Indexes

- `users_account_status_index`: `CREATE INDEX users_account_status_index ON public.users USING btree (account_status)`
- `users_email_unique`: `CREATE UNIQUE INDEX users_email_unique ON public.users USING btree (email)`
- `users_pkey`: `CREATE UNIQUE INDEX users_pkey ON public.users USING btree (id)`
- `users_role_index`: `CREATE INDEX users_role_index ON public.users USING btree (role)`

## webauthn

| Column | PostgreSQL type | Nullable |
| --- | --- | --- |
| `id` | `character varying(255)` | No |
| `user_id` | `character varying(255)` | No |
| `name` | `character varying(255)` | No |
| `credential` | `json` | Yes |
| `last_login` | `timestamp(0) without time zone` | Yes |
| `created_at` | `timestamp(0) without time zone` | Yes |
| `updated_at` | `timestamp(0) without time zone` | Yes |

### Constraints

- `webauthn_pkey`: `PRIMARY KEY (id)`

### Indexes

- `webauthn_pkey`: `CREATE UNIQUE INDEX webauthn_pkey ON public.webauthn USING btree (id)`

