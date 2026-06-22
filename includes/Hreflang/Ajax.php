<?php
/**
 * AJAX handlery pro Hreflang Manager.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Hreflang_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_seob_hreflang_load',     [ $this, 'load_groups' ] );
		add_action( 'wp_ajax_seob_hreflang_save',     [ $this, 'save_group' ] );
		add_action( 'wp_ajax_seob_hreflang_delete',   [ $this, 'delete_group' ] );
		add_action( 'wp_ajax_seob_hreflang_search',   [ $this, 'search_posts' ] );
		add_action( 'wp_ajax_seob_hreflang_validate', [ $this, 'validate' ] );
	}

	private function check_nonce(): void {
		if ( ! check_ajax_referer( 'seob_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Neplatný token.' ], 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nedostatečná oprávnění.' ], 403 );
		}
	}

	public function load_groups(): void {
		$this->check_nonce();

		global $wpdb;

		$groups_table  = SEOB_Database::hreflang_groups_table();
		$members_table = SEOB_Database::hreflang_members_table();

		$groups = $wpdb->get_results(
			"SELECT id, name, created_at FROM {$groups_table} ORDER BY id ASC",
			ARRAY_A
		);

		if ( ! $groups ) {
			wp_send_json_success( [
				'groups'       => [],
				'conflict'     => SEOB_Hreflang_Manager::has_conflict(),
				'multilingual' => SEOB_Hreflang_Manager::detect_multilingual(),
			] );
		}

		$group_ids    = array_column( $groups, 'id' );
		$placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$members = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.id, m.group_id, m.page_id, m.locale, m.is_x_default,
				        p.post_title, p.post_status
				 FROM {$members_table} m
				 LEFT JOIN {$wpdb->posts} p ON p.ID = m.page_id
				 WHERE m.group_id IN ({$placeholders})
				 ORDER BY m.group_id ASC, m.id ASC",
				...$group_ids
			),
			ARRAY_A
		);

		$members_by_group = [];
		foreach ( $members as $m ) {
			$gid = (int) $m['group_id'];
			if ( ! isset( $members_by_group[ $gid ] ) ) {
				$members_by_group[ $gid ] = [];
			}
			$members_by_group[ $gid ][] = [
				'id'           => (int) $m['id'],
				'page_id'      => (int) $m['page_id'],
				'locale'       => $m['locale'],
				'is_x_default' => (int) $m['is_x_default'],
				'title'        => $m['post_title'] ?: '(ID ' . $m['page_id'] . ')',
				'edit_link'    => get_edit_post_link( (int) $m['page_id'], 'raw' ),
				'view_link'    => get_permalink( (int) $m['page_id'] ),
				'post_status'  => $m['post_status'],
			];
		}

		$result = [];
		foreach ( $groups as $g ) {
			$gid      = (int) $g['id'];
			$result[] = [
				'id'         => $gid,
				'name'       => $g['name'],
				'created_at' => $g['created_at'],
				'members'    => $members_by_group[ $gid ] ?? [],
			];
		}

		wp_send_json_success( [
			'groups'       => $result,
			'conflict'     => SEOB_Hreflang_Manager::has_conflict(),
			'multilingual' => SEOB_Hreflang_Manager::detect_multilingual(),
		] );
	}

	public function save_group(): void {
		$this->check_nonce();

		global $wpdb;

		$id          = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$members_raw = isset( $_POST['members'] ) ? wp_unslash( $_POST['members'] ) : '[]';

		if ( '' === $name ) {
			wp_send_json_error( [ 'message' => 'Název skupiny je povinný.' ] );
		}

		$members = json_decode( $members_raw, true );

		if ( ! is_array( $members ) ) {
			wp_send_json_error( [ 'message' => 'Neplatná data skupiny.' ] );
		}

		$page_ids = [];
		foreach ( $members as $m ) {
			$page_id = absint( $m['page_id'] ?? 0 );
			$locale  = sanitize_text_field( $m['locale'] ?? '' );

			if ( ! $page_id || ! $locale ) {
				wp_send_json_error( [ 'message' => 'Každý člen musí mít stránku a locale.' ] );
			}

			if ( in_array( $page_id, $page_ids, true ) ) {
				wp_send_json_error( [ 'message' => 'Stránka je ve skupině uvedena vícekrát.' ] );
			}

			$page_ids[] = $page_id;
		}

		$groups_table  = SEOB_Database::hreflang_groups_table();
		$members_table = SEOB_Database::hreflang_members_table();

		if ( $id ) {
			$wpdb->update( $groups_table, [ 'name' => $name ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
		} else {
			$wpdb->insert( $groups_table, [
				'name'       => $name,
				'created_at' => current_time( 'mysql' ),
			], [ '%s', '%s' ] );
			$id = (int) $wpdb->insert_id;
		}

		$wpdb->delete( $members_table, [ 'group_id' => $id ], [ '%d' ] );

		foreach ( $members as $m ) {
			$wpdb->insert( $members_table, [
				'group_id'     => $id,
				'page_id'      => absint( $m['page_id'] ),
				'locale'       => sanitize_text_field( $m['locale'] ),
				'is_x_default' => empty( $m['is_x_default'] ) ? 0 : 1,
			], [ '%d', '%d', '%s', '%d' ] );
		}

		wp_send_json_success( [ 'id' => $id, 'message' => 'Skupina uložena.' ] );
	}

	public function delete_group(): void {
		$this->check_nonce();

		global $wpdb;

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( [ 'message' => 'Chybí ID skupiny.' ] );
		}

		$groups_table  = SEOB_Database::hreflang_groups_table();
		$members_table = SEOB_Database::hreflang_members_table();

		$wpdb->delete( $members_table, [ 'group_id' => $id ], [ '%d' ] );
		$wpdb->delete( $groups_table, [ 'id' => $id ], [ '%d' ] );

		wp_send_json_success( [ 'message' => 'Skupina smazána.' ] );
	}

	public function search_posts(): void {
		$this->check_nonce();

		$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';

		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( [ 'posts' => [] ] );
		}

		$query = new WP_Query( [
			's'              => $term,
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'orderby'        => 'relevance',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );

		$results = [];
		foreach ( $query->posts as $post_id ) {
			$results[] = [
				'id'    => (int) $post_id,
				'title' => get_the_title( $post_id ),
				'url'   => get_permalink( $post_id ),
			];
		}

		wp_send_json_success( [ 'posts' => $results ] );
	}

	public function validate(): void {
		$this->check_nonce();

		global $wpdb;

		$members_table = SEOB_Database::hreflang_members_table();
		$groups_table  = SEOB_Database::hreflang_groups_table();

		$issues = [];

		$duplicates = $wpdb->get_results(
			"SELECT page_id, COUNT(*) as cnt FROM {$members_table} GROUP BY page_id HAVING cnt > 1",
			ARRAY_A
		);

		foreach ( $duplicates as $dup ) {
			$issues[] = sprintf(
				'Stránka „%s" (ID %d) je ve více skupinách.',
				get_the_title( (int) $dup['page_id'] ),
				$dup['page_id']
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$members = $wpdb->get_results(
			"SELECT m.page_id, g.name as group_name, p.post_status
			 FROM {$members_table} m
			 LEFT JOIN {$groups_table} g ON g.id = m.group_id
			 LEFT JOIN {$wpdb->posts} p ON p.ID = m.page_id",
			ARRAY_A
		);

		foreach ( $members as $m ) {
			if ( 'publish' !== $m['post_status'] ) {
				$issues[] = sprintf(
					'Stránka „%s" (ID %d) ve skupině „%s" není publikovaná (stav: %s).',
					get_the_title( (int) $m['page_id'] ),
					$m['page_id'],
					$m['group_name'],
					$m['post_status'] ?: 'neexistuje'
				);
			}
		}

		wp_send_json_success( [
			'issues' => $issues,
			'ok'     => empty( $issues ),
		] );
	}
}
