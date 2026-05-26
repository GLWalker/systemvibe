<?php
declare( strict_types = 1 );

namespace SystemVibe\Engine;

/**
 * The Rule Evaluator
 *
 * Takes raw facts gathered by the Scanner and evaluates them against the Constitution (rules.json).
 */
final class RuleEvaluator {

	/**
	 * @var array
	 */
	private array $rules = array();

	public function __construct() {
		$this->load_rules();
	}

	private function load_rules(): void {
		$filepath = SYSTEMVIBE_DIR . 'src/Knowledge/rules.json';
		if ( file_exists( $filepath ) ) {
			$data = json_decode( file_get_contents( $filepath ), true );
			if ( isset( $data['rules'] ) && is_array( $data['rules'] ) ) {
				$this->rules = $data['rules'];
			}
		}
	}

	/**
	 * Evaluates the facts.
	 *
	 * @param array $facts The raw facts from Scanner.
	 * @return array Array of findings.
	 */
	public function evaluate( array $facts ): array {
		$findings = array();

		// For Phase II, we evaluate ability rules against the 'abilities' facts.
		if ( isset( $facts['abilities'] ) ) {
			$findings = array_merge( $findings, $this->evaluate_abilities( $facts['abilities'] ) );
		}

		return $findings;
	}

	private function evaluate_abilities( array $abilities ): array {
		$findings = array();
		$ability_rules = array_filter( $this->rules, fn( $r ) => $r['target'] === 'ability' );

		foreach ( $abilities as $name => $ability ) {
			foreach ( $ability_rules as $rule ) {
				$passed = $this->test_ability_rule( $rule['id'], $ability );
				
				$findings[] = array(
					'rule_id'  => $rule['id'],
					'target'   => $name,
					'passed'   => $passed,
					'severity' => $rule['severity'],
				);
			}
		}

		return $findings;
	}

	private function test_ability_rule( string $rule_id, array $ability ): bool {
		switch ( $rule_id ) {
			case 'ability.registered':
				return true; // If it's in the array, it registered successfully
				
			case 'ability.has.label':
				return ! empty( $ability['label'] );
			
			case 'ability.has.description':
				return ! empty( $ability['description'] );
			
			case 'ability.has.category':
				return ! empty( $ability['category'] );
			
			case 'ability.has.valid_input_schema':
				if ( empty( $ability['input_schema'] ) ) {
					return true;
				}
				return is_array( $ability['input_schema'] ) && isset( $ability['input_schema']['type'] ) && $ability['input_schema']['type'] === 'object';
			
			case 'ability.has.valid_output_schema':
				return is_array( $ability['output_schema'] ) && isset( $ability['output_schema']['type'] ) && $ability['output_schema']['type'] === 'object';
				
			case 'ability.rest_visibility_known':
				return isset( $ability['show_in_rest'] );
			
			default:
				return false;
		}
	}
}
