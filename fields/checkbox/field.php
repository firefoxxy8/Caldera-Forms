<?php echo $wrapper_before; ?>
	<?php echo $field_label; ?>
	<?php echo $field_before; ?>
		<?php

		$req_class = '';
		$parsley_req = '';
		if( !empty( $field['required'] ) ){
			$req_class = ' option-required';
			$parsley_req = 'data-parsley-required="true" data-parsley-group="' . esc_attr( $field_id ) . '" data-parsley-multiple="' . esc_attr( $field_id ). '"';
		}

		/////// Note this needs to be updated in order to handle array field_values !!
		// If the field value set doesn't exist, set it back to null
		if ( ! empty( $field[ 'config' ][ 'option' ] ) ) {
			$option_values = Caldera_Forms_Field_Util::find_option_values( $field );

			if ( ! in_array( $field_value, $option_values ) ) {
				$field_value = null;
			}
		}

		if ( ! empty( $field[ 'config' ][ 'option' ] ) ) {

			// If default exists and val doesn't, set it
			if ( isset( $field[ 'config' ] ) && isset( $field[ 'config' ][ 'default' ] ) && isset( $field[ 'config' ][ 'option' ][ $field[ 'config' ][ 'option' ] ] ) ) {
				if ( $field_value == null ) {
					$field_value = (array) $field[ 'config' ][ 'option' ][ $field[ 'config' ][ 'option' ] ][ 'value' ];
				}

			}
						
			foreach($field['config']['option'] as $option_key=>$option){
				if(!isset($option['value'])){
					$option['value'] = $option['label'];
				}
				?>
				<?php if(empty($field['config']['inline'])){ ?>
					<div class="checkbox">
				<?php } ?>

				  <label<?php if(!empty($field['config']['inline'])){ ?> class="checkbox-inline"<?php } ?> for="<?php echo esc_attr( $field_id . '_' . $option_key ); ?>"><input <?php echo $parsley_req; ?> type="checkbox" data-label="<?php echo esc_attr( $option['label'] );?>" data-field="<?php echo esc_attr( $field_base_id ); ?>" id="<?php echo $field_id . '_' . $option_key; ?>" class="<?php echo $field_id . $req_class; ?>" name="<?php echo esc_attr( $field_name ); ?>[<?php echo esc_attr( $option_key ); ?>]" value="<?php echo esc_attr( $option['value'] ); ?>" <?php if( in_array( $option['value'], (array) $field_value) ){ ?>checked="checked"<?php } ?> data-type="checkbox" data-checkbox-field="<?php echo esc_attr( $field_id ); ?>"> <?php echo $option['label']; ?></label>

				  <?php if(empty($field['config']['inline'])){ ?>
					</div>
				<?php } ?>
				<?php
			}
		} ?>
		<?php echo $field_caption; ?>
	<?php echo $field_after; ?>
<?php echo $wrapper_after; ?>
