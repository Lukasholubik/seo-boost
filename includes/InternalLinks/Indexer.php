<?php
/**
 * Udržuje link-graf aktuální mezi reindexy – při uložení příspěvku přepočte jeho odkazy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_InternalLinks_Indexer {

	public function __construct() {
		add_action( 'save_post', [ $this, 'reindex_post' ], 20, 2 );
	}

	/**
	 * Při uložení publikovaného příspěvku přepočte jeho řádky v `internal_links`.
	 * Návrhy prolinkování (`link_suggestions`) se přepočítají až při dalším plném reindexu.
	 */
	public function reindex_post( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		if ( ! in_array( $post->post_type, SEOB_Audit_ScanRunner::get_audit_post_types(), true ) ) {
			return;
		}

		global $wpdb;

		$links_table = SEOB_Database::internal_links_table();
		$extractor   = new SEOB_InternalLinks_Extractor();

		$wpdb->delete( $links_table, [ 'source_id' => $post_id ], [ '%d' ] );

		foreach ( $extractor->extract_links( $post ) as $link ) {
			$wpdb->insert(
				$links_table,
				[
					'source_id'   => $post_id,
					'source_type' => $post->post_type,
					'target_id'   => $link['target_id'],
					'link_text'   => mb_substr( $link['link_text'], 0, 255 ),
				],
				[ '%d', '%s', '%d', '%s' ]
			);
		}
	}
}
