<?php

namespace SeoBoost\Tests\Unit\AiQueue;

use Brain\Monkey\Functions;
use SeoBoost\Tests\TestCase;

require_once SEOB_PLUGIN_DIR . 'includes/AiQueue/Crypt.php';

final class CryptTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_salt' )->justReturn( 'test-secret-salt' );
	}

	public function test_encrypt_and_decrypt_round_trip(): void {
		$plain     = 'sk-test-api-key-12345';
		$encrypted = \SEOB_AiQueue_Crypt::encrypt( $plain );

		$this->assertNotSame( $plain, $encrypted );
		$this->assertSame( $plain, \SEOB_AiQueue_Crypt::decrypt( $encrypted ) );
	}

	public function test_empty_input_returns_empty_string(): void {
		$this->assertSame( '', \SEOB_AiQueue_Crypt::encrypt( '' ) );
		$this->assertSame( '', \SEOB_AiQueue_Crypt::decrypt( '' ) );
	}

	public function test_encrypting_same_value_twice_yields_different_ciphertext(): void {
		$plain = 'sk-test-api-key-12345';

		$first  = \SEOB_AiQueue_Crypt::encrypt( $plain );
		$second = \SEOB_AiQueue_Crypt::encrypt( $plain );

		$this->assertNotSame( $first, $second );
		$this->assertSame( $plain, \SEOB_AiQueue_Crypt::decrypt( $first ) );
		$this->assertSame( $plain, \SEOB_AiQueue_Crypt::decrypt( $second ) );
	}
}
