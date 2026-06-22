<?php
/**
 * Adaptér pro libovolné OpenAI-compatible API (Chat Completions) - např.
 * OpenAI, Google Gemini (OpenAI-compatible endpoint) nebo Groq.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_AiQueue_OpenAi_Compatible_Provider implements SEOB_AiQueue_Provider_Interface {

	private string $endpoint;
	private string $api_key;
	private string $model;
	private int $max_tokens;

	public function __construct( string $endpoint, string $api_key, string $model, int $max_tokens ) {
		$this->endpoint   = $endpoint;
		$this->api_key    = $api_key;
		$this->model      = $model;
		$this->max_tokens = $max_tokens;
	}

	/**
	 * @return string|WP_Error
	 */
	public function complete( string $prompt ) {
		if ( '' === $this->endpoint || '' === $this->api_key || '' === $this->model ) {
			return new WP_Error( 'seob_ai_not_configured', __( 'AI asistent není nakonfigurován (chybí endpoint, API klíč nebo model).', 'seo-boost' ) );
		}

		$url = rtrim( $this->endpoint, '/' ) . '/chat/completions';

		// SSRF ochrana: endpoint musí být http/https a nesmí mířit na privátní síť.
		// Administrátor nastavuje endpoint – i tak blokujeme metadata API a lokální sítě.
		$parsed_scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$parsed_host   = (string) wp_parse_url( $url, PHP_URL_HOST );

		if ( ! in_array( $parsed_scheme, [ 'http', 'https' ], true ) || '' === $parsed_host ) {
			return new WP_Error( 'seob_ai_invalid_endpoint', __( 'Neplatný AI endpoint – povoleny jsou pouze https/http URL.', 'seo-boost' ) );
		}

		// Blokuj privátní/rezervované IP rozsahy (AWS metadata 169.254.x, loopback, LAN).
		if ( (bool) preg_match( '/^(localhost|127\.|0\.|169\.254\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.|::1)/i', $parsed_host ) ) {
			return new WP_Error( 'seob_ai_invalid_endpoint', __( 'Neplatný AI endpoint – privátní a lokální adresy nejsou povoleny.', 'seo-boost' ) );
		}

		$response = wp_remote_post(
			$url,
			[
				'timeout' => 30,
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->api_key,
				],
				'body'    => wp_json_encode(
					[
						'model'      => $this->model,
						'messages'   => [
							[
								'role'    => 'user',
								'content' => $prompt,
							],
						],
						'max_tokens' => $this->max_tokens,
					]
				),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			return new WP_Error( 'seob_ai_request_failed', sprintf( /* translators: %d: HTTP status code */ __( 'AI požadavek selhal (HTTP %d).', 'seo-boost' ), $code ) );
		}

		$content = $body['choices'][0]['message']['content'] ?? '';

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return new WP_Error( 'seob_ai_empty_response', __( 'AI vrátila prázdnou odpověď.', 'seo-boost' ) );
		}

		return trim( $content );
	}
}
