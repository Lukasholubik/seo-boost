<?php
/**
 * CRUD nad tabulkou seo_booster_ai_queue.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_AiQueue_Repository {

	public const STATUS_PENDING  = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_REJECTED = 'rejected';

	/**
	 * Vloží nový návrh do fronty se stavem `pending`.
	 */
	public static function insert( int $object_id, string $field, string $suggestion ): int {
		global $wpdb;

		$wpdb->insert(
			SEOB_Database::ai_queue_table(),
			[
				'object_id'  => $object_id,
				'field'      => $field,
				'suggestion' => $suggestion,
				'status'     => self::STATUS_PENDING,
				'created_at' => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%s', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Vrátí položky fronty s daným stavem, nejnovější první.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_list( string $status = self::STATUS_PENDING, int $limit = 50 ): array {
		global $wpdb;

		$table = SEOB_Database::ai_queue_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$status,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Vrátí jednu položku fronty, nebo null.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;

		$table = SEOB_Database::ai_queue_table();

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	/**
	 * Nastaví stav položky (approved/rejected) a kdo/kdy ji posoudil.
	 */
	public static function set_status( int $id, string $status, int $reviewed_by ): bool {
		global $wpdb;

		$updated = $wpdb->update(
			SEOB_Database::ai_queue_table(),
			[
				'status'      => $status,
				'reviewed_by' => $reviewed_by,
				'reviewed_at' => current_time( 'mysql' ),
			],
			[ 'id' => $id ],
			[ '%s', '%d', '%s' ],
			[ '%d' ]
		);

		return false !== $updated;
	}

	/**
	 * Spočítá položky fronty s daným stavem.
	 */
	public static function count_by_status( string $status ): int {
		global $wpdb;

		$table = SEOB_Database::ai_queue_table();

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}
}
