<?php

namespace SeoBoost\Tests\Unit\AiQueue;

use SeoBoost\Tests\TestCase;

require_once SEOB_PLUGIN_DIR . 'includes/AiQueue/PromptBuilder.php';

final class PromptBuilderTest extends TestCase {

	public function test_for_title_includes_inputs_and_length_instruction(): void {
		$prompt = \SEOB_AiQueue_Prompt_Builder::for_title( 'Úvodní stránka', 'Starý title', 'Obsah stránky...' );

		$this->assertStringContainsString( 'Úvodní stránka', $prompt );
		$this->assertStringContainsString( 'Starý title', $prompt );
		$this->assertStringContainsString( 'Obsah stránky...', $prompt );
		$this->assertStringContainsString( '60 znaků', $prompt );
		$this->assertStringContainsString( 'češtině', $prompt );
	}

	public function test_for_title_handles_empty_current_value(): void {
		$prompt = \SEOB_AiQueue_Prompt_Builder::for_title( 'Úvodní stránka', '', 'Obsah stránky...' );

		$this->assertStringContainsString( '(není nastaven)', $prompt );
	}

	public function test_for_description_includes_inputs_and_length_instruction(): void {
		$prompt = \SEOB_AiQueue_Prompt_Builder::for_description( 'Úvodní stránka', 'Starý popis', 'Obsah stránky...' );

		$this->assertStringContainsString( 'Úvodní stránka', $prompt );
		$this->assertStringContainsString( 'Starý popis', $prompt );
		$this->assertStringContainsString( 'Obsah stránky...', $prompt );
		$this->assertStringContainsString( '155 znaků', $prompt );
	}

	public function test_for_description_handles_empty_current_value(): void {
		$prompt = \SEOB_AiQueue_Prompt_Builder::for_description( 'Úvodní stránka', '', 'Obsah stránky...' );

		$this->assertStringContainsString( '(není nastavena)', $prompt );
	}

	public function test_for_alt_includes_inputs_and_length_instruction(): void {
		$prompt = \SEOB_AiQueue_Prompt_Builder::for_alt( 'Úvodní stránka', 'foto-vyrobek.jpg', 'Okolní text...' );

		$this->assertStringContainsString( 'Úvodní stránka', $prompt );
		$this->assertStringContainsString( 'foto-vyrobek.jpg', $prompt );
		$this->assertStringContainsString( 'Okolní text...', $prompt );
		$this->assertStringContainsString( '125 znaků', $prompt );
	}
}
