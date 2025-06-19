<?php

namespace Comment_Notifications\Settings;

class Field_Checkbox extends Field {

	public function sanitize( $value ): ?string {
		if ( ! empty( $value ) ) {
			return '1';
		}

		return null;
	}

	public function render(): string {
		$parts = [];

		$parts[] = sprintf(
			'<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
			esc_attr( $this->name() ),
			checked( $this->get(), '1', false ),
			esc_attr( $this->label() )
		);

		$help = $this->help();

		if ( ! empty( $help ) ) {
			$parts[] = sprintf( '<p class="description">%s</p>', wp_kses_post( $help ) );
		}

		return implode( '', $parts );
	}
}
