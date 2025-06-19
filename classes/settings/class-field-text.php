<?php

namespace Comment_Notifications\Settings;

class Field_Text extends Field {

	public function sanitize( $value ): ?string {
		if ( ! isset( $value ) ) {
			return null;
		}

		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		return sanitize_text_field( $value );
	}

	public function render(): string {
		$parts = [];

		$parts[] = sprintf(
			'<input type="text" name="%s" value="%s" placeholder="%s" class="%s" />',
			esc_attr( $this->name() ),
			esc_attr( $this->get() ),
			esc_attr( $this->setting( 'placeholder' ) ),
			esc_attr( $this->setting( 'input_classes', 'regular-text' ) )
		);

		$help = $this->help();

		if ( ! empty( $help ) ) {
			$parts[] = sprintf( '<p class="description">%s</p>', wp_kses_post( $help ) );
		}

		return implode( '', $parts );
	}
}
