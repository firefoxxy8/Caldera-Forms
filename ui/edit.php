<?php

global $field_type_list, $field_type_templates;

if( ! isset( $_GET['edit'] ) || ! is_string( $_GET['edit'] ) ){
	wp_die( esc_html__( 'Invalid form ID', 'caldera-forms'  ) );
}
// Load element
$element = Caldera_Forms_Forms::get_form( $_GET['edit'] );
if( empty( $element ) || ! is_array( $element ) ){
	wp_die( esc_html__( 'Invalid form', 'caldera-forms'  ) );
}

/**
 * Filter which Magic Tags are available in the form editor
 *
 *
 * @since 1.3.2
 *
 * @param array $tags Array of magic registered tags 
 * @param array $form_id for which this applies.
 */
$magic_tags = apply_filters( 'caldera_forms_get_magic_tags', array(), $element['ID'] );

//dump($element);
if(empty($element['success'])){
	$element['success'] = esc_html__( 'Form has successfully been submitted. Thank you.', 'caldera-forms' );
}

if(!isset($element['db_support'])){
	$element['db_support'] = 1;
}


/**
 * Convert existing field conditions if old method used
 *
 * @since 1.3.0
 */
if( empty( $element['conditional_groups'] ) ){
	
	$element['conditional_groups'] = array();
	if( !empty( $element['fields'] ) ){
		foreach( $element['fields'] as $field_id=>$field ){

			if( !empty( $field['conditions'] ) && !empty( $field['conditions']['type'] ) ){

				if( empty( $field['conditions']['group'] ) ){
					continue;
				}
				$element['conditional_groups']['conditions'][ 'con_' . $field['ID'] ] = array(
					'id' => 'con_' . $field['ID'],
					'name'	=> $field['label'],
					'type'	=> $field['conditions']['type'],
					'fields'=> array(),
					'group' => array()
				);

				foreach( $field['conditions']['group'] as $groups_id=>$groups ){
					foreach( $groups as $group_id => $group ){
						$element['conditional_groups']['conditions'][ 'con_' . $field['ID'] ]['fields'][ $group_id ] = $group['field'];
						$element['conditional_groups']['conditions'][ 'con_' . $field['ID'] ]['group'][ $groups_id ][ $group_id ] = array(
							'parent'	=>	$groups_id,
							'field'		=>	$group['field'],
							'compare'	=>	$group['compare'],
							'value'		=>	$group['value']
						);
					}
				}
				$element['fields'][ $field_id ]['conditions'] = array(
					'type' => 'con_' . $field['ID']
				);
			}
		}
	}
}

if ( ! isset( $element['fields'] ) ) {
	$element['fields'] = array();
}

$element['conditional_groups']['fields'] = $element['fields'];

// place nonce field
wp_nonce_field( 'cf_edit_element', 'cf_edit_nonce' );

// Init check
echo "<input id=\"last_updated_field\" name=\"config[_last_updated]\" value=\"" . date('r') . "\" type=\"hidden\">";
echo "<input id=\"form_id_field\" name=\"config[ID]\" value=\"" . $_GET['edit'] . "\" type=\"hidden\">";

do_action('caldera_forms_edit_start', $element);

// Get Fieldtpyes
$field_types = apply_filters( 'caldera_forms_get_field_types', array() );
// sort fields

// Get Elements
$panel_extensions = apply_filters( 'caldera_forms_get_panel_extensions', array() );


$field_type_list = array();
$field_type_templates = array();
$field_type_defaults = array(
	"var fieldtype_defaults = {};"
);

// options based template
$field_options_template = "
<div class=\"caldera-config-group caldera-config-group-full\">
	<div class=\"caldera-config-group\">
		<div class=\"caldera-config-field\">
			<label><input id=\"{{_id}}_auto\" type=\"checkbox\" class=\"auto-populate-options field-config\" name=\"{{_name}}[auto]\" value=\"1\" {{#if auto}}checked=\"checked\"{{/if}}> ".esc_html__( 'Auto Populate', 'caldera-forms' )."</label>
		</div>
	</div>
</div>
{{#if auto}}{{#script}}jQuery('#{{_id}}_auto').trigger('change');{{/script}}{{/if}}
<div class=\"caldera-config-group-auto-options\" style=\"display:none;\">
	<div class=\"caldera-config-group\">
		<label>". esc_html__( 'Auto Type', 'caldera-forms' ) . "</label>
		<div class=\"caldera-config-field\">
			<select class=\"block-input field-config auto-populate-type\" name=\"{{_name}}[auto_type]\">
				<option value=\"\">" . esc_html__( 'Select a source', 'caldera-forms' ) . "</option>
				<option value=\"post_type\"{{#is auto_type value=\"post_type\"}} selected=\"selected\"{{/is}}>" . esc_html__( 'Post Type', 'caldera-forms' ) . "</option>
				<option value=\"taxonomy\"{{#is auto_type value=\"taxonomy\"}} selected=\"selected\"{{/is}}>" . esc_html__( 'Taxonomy', 'caldera-forms' ) . "</option>";
				ob_start();

				/**
				 * Runs after default field auto-population types options are outputted, inside of the select element.
				 *
				 * Use this to add new options in UI for auto-population sources
				 *
				 * @since unknown
				 */
				do_action( 'caldera_forms_autopopulate_types' );
				$field_options_template .= ob_get_clean() . "
			</select>
		</div>
	</div>
	
	<div class=\"caldera-config-group caldera-config-group-auto-taxonomy auto-populate-type-panel\" style=\"display:none;\">
		<label>". esc_html__( 'Taxonomy', 'caldera-forms' )."</label>
		<div class=\"caldera-config-field\">
			<select class=\"block-input field-config\" name=\"{{_name}}[taxonomy]\">";

			$taxonomies = get_taxonomies();

	    	foreach($taxonomies as $tax_type=>$tax_name){
	    		$field_options_template .= "<option value=\"" . $tax_type . "\" {{#is taxonomy value=\"" . $tax_type . "\"}}selected=\"selected\"{{/is}}>" . $tax_name . "</option>\r\n";
	    	}
	    	
			$field_options_template .= "</select>

		</div>
	</div>

	<div class=\"caldera-config-group caldera-config-group-auto-post_type auto-populate-type-panel\" style=\"display:none;\">
		<label>".esc_html__( 'Post Type', 'caldera-forms' ) ."</label>
		<div class=\"caldera-config-field\">
			<select class=\"block-input field-config\" name=\"{{_name}}[post_type]\">";

			$post_types = get_post_types(array(), 'objects');

	    	foreach($post_types as $type){
	    		$field_options_template .= "<option value=\"" . $type->name . "\" {{#is post_type value=\"" . $type->name . "\"}}selected=\"selected\"{{/is}}>" . $type->labels->name . "</option>\r\n";
	    	}

			$field_options_template .= "</select>

		</div>
	</div>

	<div class=\"caldera-config-group caldera-config-group-auto-taxonomy caldera-config-group-auto-post_type auto-populate-type-panel\" style=\"display:none;\">
		<label>". esc_html__( 'Value', 'caldera-forms' )."</label>
		<div class=\"caldera-config-field\">
			<select class=\"block-input field-config\" name=\"{{_name}}[value_field]\">
				<option value=\"name\" {{#is value_field value=\"name\"}}selected=\"selected\"{{/is}}>Name</option>\r\n
				<option value=\"id\" {{#is value_field value=\"id\"}}selected=\"selected\"{{/is}}>ID</option>\r\n
	    	</select>
		</div>
	</div>
	<div class=\"caldera-config-group caldera-config-group-auto-taxonomy auto-populate-type-panel\" style=\"display:none;\">
		<label>". esc_html__( 'Orderby', 'caldera-forms' )."</label>
		<div class=\"caldera-config-field\">
			<select class=\"block-input field-config\" name=\"{{_name}}[orderby_tax]\">
				<option value=\"count\" {{#is value_field value=\"count\"}}selected=\"selected\"{{/is}}>
					" . __( 'Count', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"id\" {{#is value_field value=\"id\"}}selected=\"selected\"{{/is}}>
					" . __( 'ID', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"name\" {{#is value_field value=\"name\"}}selected=\"selected\"{{/is}}>
					" . __( 'Name', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"slug\" {{#is value_field value=\"slug\"}}selected=\"selected\"{{/is}}>
					" . __( 'Slug', 'caldera-forms'  ) ."
				</option>\r\n
	    	</select>
		</div>
	</div>
	<div class=\"caldera-config-group caldera-config-group-auto-post_type auto-populate-type-panel\" style=\"display:none;\">
		<label>". esc_html__( 'Orderby', 'caldera-forms' )."</label>
		<div class=\"caldera-config-field\">
			<select class=\"block-input field-config\" name=\"{{_name}}[orderby_post]\">
				<option value=\"ID\" {{#is value_field value=\"ID\"}}selected=\"selected\"{{/is}}>
					" . __( 'ID', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"name\" {{#is value_field value=\"name\"}}selected=\"selected\"{{/is}}>
					" . __( 'Name (post slug)', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"author\" {{#is value_field value=\"author\"}}selected=\"selected\"{{/is}}>
					" . __( 'Author', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"title\" {{#is value_field value=\"title\"}}selected=\"selected\"{{/is}}>
					" . __( 'Title', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"date\" {{#is value_field value=\"date\"}}selected=\"selected\"{{/is}}>
					" . __( 'Publish Date', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"modified\" {{#is value_field value=\"modified\"}}selected=\"selected\"{{/is}}>
					" . __( 'Modified Date', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"parent\" {{#is value_field value=\"parent\"}}selected=\"selected\"{{/is}}>
					" . __( 'Parent ID', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"comment_count\" {{#is value_field value=\"comment_count\"}}selected=\"selected\"{{/is}}>
					" . __( 'Comment Count', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"menu_order\" {{#is value_field value=\"menu_order\"}}selected=\"selected\"{{/is}}>
					" . __( 'Menu Order', 'caldera-forms'  ) ."
				</option>\r\n
	    	</select>
		</div>
	</div>
	<div class=\"caldera-config-group caldera-config-group-auto-taxonomy caldera-config-group-auto-post_type auto-populate-type-panel\" style=\"display:none;\">
		<label>". esc_html__( 'Order', 'caldera-forms' )."</label>
		<div class=\"caldera-config-field\">
			<select class=\"block-input field-config\" name=\"{{_name}}[order]\">
				<option value=\"ASC\" {{#is value_field value=\"ASC\"}}selected=\"selected\"{{/is}}>
					" . __( 'Ascending', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"DESC\" {{#is value_field value=\"DESC\"}}selected=\"selected\"{{/is}}>
					" . __( 'Descending', 'caldera-forms'  ) ."
				</option>\r\n
	    	</select>
		</div>
	</div>


	";
	ob_start();

	/**
	 * Runs after default options for auto-populate fields
	 *
	 * Use this to add new options in UI when making custom aut-population types
	 *
	 * @since unknown
	 */
	do_action( 'caldera_forms_autopopulate_type_config' );
	$field_options_template .= ob_get_clean() . "

</div>
<div class=\"caldera-config-group-toggle-options\" {{#if auto}}style=\"display:none;\"{{/if}}>
	<div class=\"caldera-config-group caldera-config-group-full\">
		<button type=\"button\" class=\"button add-toggle-option\" style=\"width: 220px;\">" . esc_html__( 'Add Option', 'caldera-forms' ) . "</button>		
		<button type=\"button\" data-bulk=\"#{{_id}}_bulkwrap\" class=\"button add-toggle-option\" style=\"width: 120px;\">" . esc_html__( 'Bulk Insert', 'caldera-forms' ) . "</button>
		<div id=\"{{_id}}_bulkwrap\" style=\"display:none; margin-top:10px;\">
		<textarea style=\"resize:vertical; height:200px;\" class=\"block-input\" id=\"{{_id}}_batch\"></textarea>
		<p class=\"description\">" . esc_html__( 'Single option per line. These replace the current list.', 'caldera-forms' ) . "</p>
		<button type=\"button\" data-options=\"#{{_id}}_batch\" class=\"button block-button add-toggle-option\" style=\"margin: 10px 0;\">" . esc_html__( 'Insert Options', 'caldera-forms' ) . "</button>
		</div>
	</div>
	<div class=\"caldera-config-group caldera-config-group-full\">
	<label style=\"padding: 10px;\"><input type=\"radio\" class=\"toggle_set_default field-config\" name=\"{{_name}}[default]\" value=\"\" {{#unless default}}checked=\"checked\"{{/unless}}> " . esc_html__( 'No Default', 'caldera-forms' ) . "</label>
	<label class=\"pull-right\" style=\"padding: 10px;\"><input type=\"checkbox\" class=\"toggle_show_values field-config\" name=\"{{_name}}[show_values]\" value=\"1\" {{#if show_values}}checked=\"checked\"{{/if}}> " . esc_html__( 'Show Values', 'caldera-forms' ) . "</label>
	</div>
	<div class=\"caldera-config-group-option-labels\" {{#unless show_values}}style=\"display:none;\"{{/unless}}>
		<span style=\"display: block; clear: left; padding-left: 65px; float: left; width: 142px;\">" . esc_html__( 'Value', 'caldera-forms' ) . "</span>
		<span style=\"float: left;\">" . esc_html__( 'Label', 'caldera-forms' ) . "</span>
	</div>
	<div class=\"caldera-config-group caldera-config-group-full toggle-options\">
		{{#each option}}
		<div class=\"toggle_option_row\">
			<i class=\"dashicons dashicons-sort\" style=\"padding: 4px 9px;\"></i>
			<input type=\"radio\" class=\"toggle_set_default field-config\" name=\"{{../_name}}[default]\" value=\"{{@key}}\" {{#is ../default value=\"@key\"}}checked=\"checked\"{{/is}}>
			<span style=\"position: relative; display: inline-block;\"><input{{#unless ../show_values}} style=\"display:none;\"{{/unless}} type=\"text\" class=\"toggle_value_field field-config magic-tag-enabled\" name=\"{{../_name}}[option][{{@key}}][value]\" value=\"{{#if ../show_values}}{{value}}{{else}}{{label}}{{/if}}\" placeholder=\"value\"></span>
			<input{{#unless ../show_values}} style=\"width:245px;\"{{/unless}} type=\"text\" data-option=\"{{@key}}\" class=\"toggle_label_field field-config\" name=\"{{../_name}}[option][{{@key}}][label]\" value=\"{{label}}\" placeholder=\"label\">
			<button class=\"button button-small toggle-remove-option\" type=\"button\"><i class=\"icn-delete\"></i></button>		
		</div>
		{{/each}}
	</div>
</div>
";

$default_template = "
<div class=\"caldera-config-group\">
	<label>Default</label>
	<div class=\"caldera-config-field\">
		<input type=\"text\" class=\"block-input field-config\" name=\"{{_name}}[default]\" value=\"{{default}}\">
	</div>
</div>
";


// type list
$field_type_list = array(
	esc_html__( 'Select', 'caldera-forms' )       => array(),
	esc_html__( 'File', 'caldera-forms' )         => array(),
	esc_html__( 'Content', 'caldera-forms' )      => array(),
	esc_html__( 'Special', 'caldera-forms' )      => array(),
	esc_html__( 'Discontinued', 'caldera-forms' ) => array(),
);
// Build Field Types List
foreach($field_types as $field_slug=>$config){

	if(!file_exists($config['file'])){
		if(!function_exists($config['file'])){
			continue;
		}
	}

	$categories = array();
	if(!empty($config['category'])){
		$categories = explode(',', $config['category']);
	}
	foreach((array) $categories as $category){
		if( !isset( $field_type_list[trim($category)] ) ){
			$category = esc_html__( 'Special', 'caldera-forms' );
		}
		$field_type_list[trim($category)][$field_slug] = $config;
	}

	ob_start();
	do_action('caldera_forms_field_settings_template', $config, $field_slug );
	if(!empty($config['setup']['template'])){
		if(file_exists( $config['setup']['template'] )){
			// create config template block							
			include $config['setup']['template'];
		}
	}
	$field_type_templates[sanitize_key( $field_slug ) . "_tmpl"] = ob_get_clean();

	if(isset($config['options'])){
		if(!isset($field_type_templates[sanitize_key( $field_slug ) . "_tmpl"])){
			$field_type_templates[sanitize_key( $field_slug ) . "_tmpl"] = null;
		}

		// has configurable options - include template
		$field_type_templates[sanitize_key( $field_slug ) . "_tmpl"] .= $field_options_template;
	}

	
	if(!empty($config['setup']['default'])){
		$field_type_defaults[] = "fieldtype_defaults." . sanitize_key( $field_slug ) . "_cfg = " . json_encode($config['setup']['default']) .";";
	}
	if(!empty($config['setup']['not_supported'])){
		$field_type_defaults[] = "fieldtype_defaults." . sanitize_key( $field_slug ) . "_nosupport = " . json_encode($config['setup']['not_supported']) .";";
	}

	if(empty($config['setup']['preview']) || !file_exists( $config['setup']['preview'] )){

		// if preview is a function
		if(!empty($config['setup']['preview']) && function_exists($config['setup']['preview'])){
			$func = $config['setup']['preview'];
			$field_type_templates['preview-' . sanitize_key( $field_slug ) . "_tmpl"] = $func($config);
		}else{
			// simulate a preview with actual field file
			$field = array(
				'label'	=>	'{{label}}',
				'slug'	=>	'{{slug}}',
				'type'	=>	'{{type}}',
				'caption' => '{{caption}}',
				'config' => (!empty($config['setup']['default']) ? $config['setup']['default'] : array() )
			);

			$field_name = $field['slug'];
			$field_id = 'preview_fld_' . $field['slug'];
			$wrapper_before = "<div class=\"preview-caldera-config-group\">";
			$field_before = "<div class=\"preview-caldera-config-field\">";
			$field_after = '</div>';
			$wrapper_after = '</div>';
			$field_label = "<label for=\"" . $field_id . "\" class=\"control-label\">" . $field['label'] . "</label>\r\n";
			$field_required = "";
			$field_placeholder = 'placeholder="' . $field['label'] .'"';
			$field_caption = "<span class=\"help-block\">" . $field['caption'] . "</span>\r\n";
			
			// blank default
			$field_value = null;
			$field_class = "preview-field-config";

			ob_start();
			include $config['file'];
			$field_type_templates['preview-' . sanitize_key( $field_slug ) . "_tmpl"] = ob_get_clean();
		}
	}else{
		ob_start();
		include $config['setup']['preview'];
		$field_type_templates['preview-' . sanitize_key( $field_slug ) . "_tmpl"] = ob_get_clean();
	}


}


function field_wrapper_template($id = '{{id}}', $label = '{{label}}', $slug = '{{slug}}', $caption = '{{caption}}', $hide_label = '{{hide_label}}', $required = '{{required}}', $entry_list = '{{entry_list}}', $type = null, $config_str = '{"default":"default value"}', $conditions_str = '{"type" : ""}'){

	if(is_array($config_str)){
		$config 	= $config_str;
		$config_str = json_encode( $config_str );

	}else{
		$config = json_decode($config_str, true);
	}


	$condition_type = '';
	if(!empty($conditions_str)){
		$conditions = json_decode($conditions_str, true);
		if(!empty($conditions['type'])){
			$condition_type = $conditions['type'];
		}
		if(!empty($conditions['group'])){
			$groups = array();
			foreach ($conditions['group'] as $groupid => $group) {
				$group_tmp = array(
					'id' => $groupid,
					'type'	=> 'fields',
					'lines' => array()
				);
				if(!empty($group)){
					foreach($group as $line_id => $line){
						$group_line = $line;
						$group_line['id'] = $line_id;
						$group_tmp['lines'][] = $group_line;
					}
				}
				$groups[] = $group_tmp;
			}
			$conditions['group'] = $groups;
			$conditions_str = json_encode($conditions);
		}
	}	
	//dump($conditions,0);
	?>
	<div class="caldera-editor-field-config-wrapper caldera-editor-config-wrapper ajax-trigger" 
	
	data-request="setup_field_type" 
	data-event="field.drop"
	data-load-class="none"
	data-modal="field_setup"
	data-modal-title="<?php echo esc_html__( 'Fields', 'caldera-forms' ); ?>"
	data-template="#form-fields-selector-tmpl"
	data-modal-width="700"
	data-modal-height="680"

	id="<?php echo $id; ?>" style="display:none;">
		

		<h3 class="caldera-editor-field-title"><?php echo $label; ?>&nbsp;</h3>		
		<input type="hidden" class="field-config" name="config[fields][<?php echo $id; ?>][ID]" value="<?php echo $id; ?>">
		<div id="<?php echo $id; ?>_settings_pane" class="wrapper-instance-pane">
			<div class="caldera-config-group">
				<label for="<?php echo $id; ?>_type"><?php echo esc_html__( 'Field Type', 'caldera-forms' ); ?></label>
				<div class="caldera-config-field">
					<select class="block-input caldera-select-field-type" data-field="<?php echo $id; ?>" id="<?php echo $id; ?>_type" name="config[fields][<?php echo $id; ?>][type]" data-type="<?php echo $type; ?>">					
						<?php
						echo build_field_types($type);
						?>
					</select>
				</div>
			</div>
			<div class="caldera-config-group">
				<label for="<?php echo $id; ?>_fid"><?php echo esc_html__( 'ID', 'caldera-forms' ); ?></label>
				<div class="caldera-config-field">
					<input type="text" class="block-input field-id" id="<?php echo $id; ?>_fid" value="<?php echo $id; ?>" readonly="readonly">
				</div>
			</div>


			<div class="caldera-config-group">
				<label for="<?php echo $id; ?>_lable"><?php echo esc_html__( 'Name', 'caldera-forms' ); ?></label>
				<div class="caldera-config-field">
					<input type="text" class="block-input field-config field-label required" id="<?php echo $id; ?>_lable" name="config[fields][<?php echo $id; ?>][label]" value="<?php echo sanitize_text_field( $label ); ?>">
				</div>
			</div>

			<div class="caldera-config-group hide-label-field">
				<label for="<?php echo $id; ?>_hide_label"><?php echo esc_html__( 'Hide Label', 'caldera-forms' ); ?></label>
				<div class="caldera-config-field">
					<input type="checkbox" class="field-config field-checkbox" id="<?php echo $id; ?>_hide_label" name="config[fields][<?php echo $id; ?>][hide_label]" value="1" <?php if($hide_label === 1){ echo 'checked="checked"'; }; ?>>
				</div>
			</div>

			<div class="caldera-config-group">
				<label for="<?php echo $id; ?>_slug"><?php echo esc_html__( 'Slug', 'caldera-forms' ); ?></label>
				<div class="caldera-config-field">
					<input type="text" class="block-input field-config field-slug required" id="<?php echo $id; ?>_slug" name="config[fields][<?php echo $id; ?>][slug]" value="<?php echo $slug; ?>">
				</div>
			</div>
			<div class="caldera-config-group">
				<label for="<?php echo $id; ?>_fcond"><?php echo esc_html__( 'Condition', 'caldera-forms' ); ?></label>
				<div class="caldera-config-field">
					<select id="field-condition-type-<?php echo $id; ?>" name="config[fields][<?php echo $id; ?>][conditions][type]" data-id="<?php echo $id; ?>" class="caldera-conditionals-usetype block-input">
						<option></option>
						<optgroup class="cf-conditional-selector">
							<?php if( !in_array( $condition_type, array( 'show', 'hide','disable' ) ) ){ ?><option value="<?php echo $condition_type; ?>" selected="selected"><?php echo esc_html__( 'Disable', 'caldera-forms' ); ?></option><?php } ?></optgroup>
						</optgroup>
					</select>
				</div>
			</div>			
			<div class="caldera-config-group required-field">
				<label for="<?php echo $id; ?>_required"><?php echo esc_html__( 'Required', 'caldera-forms' ); ?></label>
				<div class="caldera-config-field">
					<input type="checkbox" class="field-config field-required field-checkbox" id="<?php echo $id; ?>_required" name="config[fields][<?php echo $id; ?>][required]" value="1" <?php if($required === 1){ echo 'checked="checked"'; }; ?>>
				</div>
			</div>

			<div class="caldera-config-group caption-field">
				<label for="<?php echo $id; ?>_caption"><?php echo esc_html__( 'Description', 'caldera-forms' ); ?></label>
				<div class="caldera-config-field">
					<input type="text" class="block-input field-config" id="<?php echo $id; ?>_caption" name="config[fields][<?php echo $id; ?>][caption]" value="<?php echo esc_html( $caption ); ?>">
				</div>
			</div>
			
			<div class="caldera-config-group entrylist-field">
				<label for="<?php echo $id; ?>_entry_list"><?php echo esc_html__( 'Show in Entry List', 'caldera-forms' ); ?></label>
				<div class="caldera-config-field">
					<input type="checkbox" class="field-config field-checkbox" id="<?php echo $id; ?>_entry_list" name="config[fields][<?php echo $id; ?>][entry_list]" value="1" <?php if($entry_list === 1){ echo 'checked="checked"'; }; ?>>
				</div>
			</div>
			<div class="caldera-config-field-setup">
			</div>
			<input type="hidden" class="field_config_string block-input" value="<?php echo htmlentities( $config_str ); ?>">
			<input type="hidden" class="field_conditions_config_string block-input ajax-trigger" data-event="none" data-autoload="true" data-request="build_conditions_config" data-template="#conditional-group-tmpl" data-id="<?php echo $id; ?>" data-target="#<?php echo $id; ?>_conditional_wrap" data-type="fields" data-callback="rebuild_field_binding" value="<?php echo htmlentities( $conditions_str ); ?>">
			<br>
			<button class="button delete-field block-button" data-confirm="<?php echo esc_html__( 'Are you sure you want to remove this field?. \'Cancel\' to stop. \'OK\' to delete', 'caldera-forms' ); ?>" type="button"><?php echo esc_html__( 'Delete Field', 'caldera-forms' ); ?></button>
		</div>

	</div>
	<?php
}

function build_field_types($default = null){
	global $field_type_list;
	

	$out = '';
	if(null === $default){
		$out .= '<option></option>';
	}

	foreach($field_type_list as $category=>$fields){

		$out .= "<optgroup label=\" ". $category . "\">\r\n";
		foreach ($fields as $field => $config) {

			$sel = "";
			if($default == $field){
				$sel = 'selected="selected"';
			}
			$out .= "<option value=\"". $field . "\" ". $sel .">" . $config['field'] . "</option>\r\n";
		}
		$out .= "</optgroup>";
	}

	return $out;

}


function field_line_template($id = '{{id}}', $label = '{{label}}', $group = '{{group}}'){
	
	ob_start();

	?>
	<li data-field="<?php echo $id; ?>" class="caldera-field-line">
		<a href="#<?php echo $id; ?>">
			<i class="icn-right pull-right"></i>
			<i class="icn-field"></i>
			<?php echo htmlentities( $label ); ?>
		</a>
		<input type="hidden" class="caldera-config-field-group" value="<?php echo $group; ?>" name="config[fields][<?php echo $id; ?>][group]" autocomplete="off">
	</li>
	<?php

	return ob_get_clean();
}


// Navigation
?>
<div class="caldera-editor-header">
	<ul class="caldera-editor-header-nav">
		<li class="caldera-editor-logo">
			<span class="dashicons-cf-logo"></span>
			<?php esc_html_e( 'Caldera Forms', 'caldera-forms' ); ?>
		</li>
		<li class="caldera-element-type-label">
			<?php echo $element['name']; ?>
		</li>
		<li>
			<a href="#settings-panel">
				<?php esc_html_e( 'Form Settings', 'caldera-forms'  ); ?>
			</a>
		</li>

	</ul>

	<div class="updated_notice_box">
		<?php esc_html_e( 'Updated Successfully', 'caldera-forms'  ); ?>
	</div>

	<button class="button button-primary caldera-header-save-button" data-active-class="none" data-load-element="#save_indicator" type="button" disabled="disabled">
		<?php esc_html_e( 'Save Form', 'caldera-forms' ); ?>
		<span id="save_indicator" class="spinner" style="position: absolute; right: -33px;"></span>
	</button>
	<a class="button caldera-header-preview-button" target="_blank" href="<?php echo esc_url( add_query_arg( 'cf_preview', $element[ 'ID' ], get_home_url() ) ); ?>">
		<?php esc_html_e( 'Preview Form', 'caldera-forms' ); ?>
	</a>

	<?php if ( ! isset( $element['mailer']['preview_email'] ) || $element['mailer']['preview_email']  ){
		$hide_email_preview = true;
	}else{
		$hide_email_preview = false;
	}?>
	<a class="button caldera-header-email-preview-button" target="_blank" href="<?php echo esc_url( add_query_arg( array(
			'cf-email-preview' => wp_create_nonce( $element[ 'ID' ] ),
			'cf-email-preview-form' => $element[ 'ID' ]
	),  get_home_url() ) ); ?>" <?php if ( $hide_email_preview ) :  echo 'aria-hidden="true" style="display:none;visibility:hidden;"'; endif; ?>>
		<?php esc_html_e( 'Preview Last Email', 'caldera-forms' ); ?>
	</a>
</div>

<div style="display: none;" class="caldera-editor-body caldera-config-editor-panel " id="settings-panel">
	<h3><?php echo __( 'Form Settings', 'caldera-forms'  ); ?></h3>
	<input type="hidden" name="config[cf_version]" value="<?php echo esc_attr( CFCORE_VER ); ?>">
	<div class="caldera-config-group">
		<label><?php echo esc_html__( 'Form Name', 'caldera-forms' ); ?> </label>
		<div class="caldera-config-field">
			<input type="text" class="field-config required" name="config[name]" value="<?php echo $element['name']; ?>" style="width:500px;" required="required">
		</div>
	</div>
	<div class="caldera-config-group">
		<label><?php echo esc_html__( 'Shortcode', 'caldera-forms' ); ?> </label>
		<div class="caldera-config-field">
			<input type="text" id="cf-shortcode-preview" value="<?php echo esc_attr( '[caldera_form id="' . $element['ID'] . '"]' ); ?>" style="width: 500px; background: #efefef; box-shadow: none; color: #8e8e8e;" readonly="readonly">
		</div>
	</div>

	<div class="caldera-config-group">
		<label><?php echo esc_html__( 'Form Description', 'caldera-forms' ); ?> </label>
		<div class="caldera-config-field">
			<textarea name="config[description]" class="field-config" style="width:500px;" rows="5"><?php echo htmlentities( $element['description'] ); ?></textarea>
		</div>
	</div>

	<div class="caldera-config-group">
		<label><?php echo esc_html__( 'State', 'caldera-forms' ); ?> </label>
		<div class="caldera-config-field">
			<label><input type="checkbox" class="field-config" name="config[form_draft]" value="1" <?php if(!empty($element['form_draft'])){ ?>checked="checked"<?php } ?>> <?php echo esc_html__( 'Deactivate / Draft', 'caldera-forms' ); ?></label>
		</div>
	</div>

	<div class="caldera-config-group">
		<label><?php echo esc_html__( 'Capture Entries', 'caldera-forms' ); ?> </label>
		<div class="caldera-config-field">
			<label><input type="radio" class="field-config" name="config[db_support]" value="1" <?php if(!empty($element['db_support'])){ ?>checked="checked"<?php } ?>> <?php echo esc_html__( 'Enable', 'caldera-forms' ); ?></label>
			<label><input type="radio" class="field-config" name="config[db_support]" value="0" <?php if(empty($element['db_support'])){ ?>checked="checked"<?php } ?>> <?php echo esc_html__( 'Disabled', 'caldera-forms' ); ?></label>
		</div>
	</div>

	<div class="caldera-config-group">
		<label>
			<?php esc_html_e('Show Entry View Page?', 'caldera-forms' ); ?>
		</label>
		<div class="caldera-config-field">
			<label><input type="radio" class="field-config pin-toggle-roles" name="config[pinned]" value="1" <?php if(!empty($element['pinned'])){ ?>checked="checked"<?php } ?>> <?php echo esc_html__( 'Enable', 'caldera-forms' ); ?></label>
			<label><input type="radio" class="field-config pin-toggle-roles" name="config[pinned]" value="0" <?php if(empty($element['pinned'])){ ?>checked="checked"<?php } ?>> <?php echo esc_html__( 'Disabled', 'caldera-forms' ); ?></label>
		</div>
		<p class="description">
			<?php esc_html_e('Create a sub-menu item of the Caldera Forms menu and a page to show entries for this form?','caldera-forms'); ?>
		</p>
	</div>

	<div id="caldera-pin-rules" <?php if(empty($element['pinned'])){ ?>style="display:none;"<?php } ?>>
		<div class="caldera-config-group">
			<label><?php echo esc_html__( 'View Entries', 'caldera-forms' ); ?> </label>
			<div class="caldera-config-field" style="max-width: 500px;">
			<label><input type="checkbox" id="pin_role_all_roles" class="field-config visible-all-roles" data-set="form_role" value="1" name="config[pin_roles][all_roles]" <?php if( !empty($element['pin_roles']['all_roles'])){ echo 'checked="checked"'; } ?>> <?php echo esc_html__( 'All'); ?></label>
			<hr>
			<?php
			global $wp_roles;
		    $all_roles = $wp_roles->roles;
		    $editable_roles = apply_filters( 'editable_roles', $all_roles);
			
			foreach($editable_roles as $role=>$role_details){
				if( 'administrator' === $role){
					continue;
				}
				?>
				<label><input type="checkbox" class="field-config form_role_role_check gen_role_check" data-set="form_role" name="config[pin_roles][access_role][<?php echo $role; ?>]" value="1" <?php if( !empty($element['pin_roles']['access_role'][$role])){ echo 'checked="checked"'; } ?>> <?php echo $role_details['name']; ?></label>
				<?php 
			}

			?>
			</div>
		</div>	
	</div>

	<div class="caldera-config-group">
		<label><?php echo esc_html__( 'Hide Form', 'caldera-forms' ); ?> </label>
		<div class="caldera-config-field">
			<label><input type="checkbox" class="field-config" name="config[hide_form]" value="1" <?php if(!empty($element['hide_form'])){ ?>checked="checked"<?php } ?>> <?php echo esc_html__( 'Enable', 'caldera-forms' ); ?>: <?php echo esc_html__( 'Hide form after successful submission', 'caldera-forms' ); ?></label>
		</div>
	</div>

	<div class="caldera-config-group">
		<label><?php echo esc_html__( 'Honeypot', 'caldera-forms' ); ?> </label>
		<div class="caldera-config-field">
			<label><input type="checkbox" class="field-config" name="config[check_honey]" value="1" <?php if(!empty($element['check_honey'])){ ?>checked="checked"<?php } ?>> <?php echo esc_html__( 'Enable', 'caldera-forms' ); ?>: <?php echo esc_html__( 'Place an invisible field to trick spambots', 'caldera-forms' ); ?></label>
		</div>
	</div>

	<div class="caldera-config-group" style="width:500px;">
		<label><?php echo esc_html__( 'Success Message', 'caldera-forms' ); ?> </label>
		<div class="caldera-config-field">
			<textarea class="field-config block-input magic-tag-enabled required" name="config[success]" required="required"><?php if (!empty($element['success'])) { echo esc_html( $element['success'] ); } else { echo esc_html__( 'Form has been successfully submitted. Thank you.', 'caldera-forms' ); } ?></textarea>
		</div>
	</div>
	<div class="caldera-config-group">
		<label><?php echo esc_html__( 'Gravatar Field', 'caldera-forms' ); ?> </label>
		<div class="caldera-config-field">
			<select style="width:500px;" class="field-config caldera-field-bind" name="config[avatar_field]" data-exclude="system" data-default="<?php if(!empty($element['avatar_field'])){ echo $element['avatar_field']; } ?>" data-type="email">
			<?php
			if(!empty($element['avatar_field'])){ echo '<option value="'.$element['avatar_field'].'"></option>'; }
			?>
			</select>
			<p class="description"><?php echo esc_html__( 'Used when viewing an entry from a non-logged in user.','caldera-forms'); ?></p>
		</div>
	</div>

	<?php do_action('caldera_forms_general_settings_panel', $element); ?>
</div>
	<div class="caldera-editor-header caldera-editor-subnav">
		<ul class="caldera-editor-header-nav">

		<?php
		// PANELS LOWER NAV

		foreach($panel_extensions as $panel_slug=>$panel){
			if(empty($panel['tabs'])){
				continue;
			}

			?>
					<?php
					// BUILD ELEMENT SETUP TABS
					if(!empty($panel['tabs'])){
						// PANEL BASED TABS
						foreach($panel['tabs'] as $group_slug=>$tab_setup){
							if($tab_setup['location'] !== 'lower'){
								continue;
							}

							$active = null;
							if(!empty($tab_setup['active'])){
								$active = " class=\"active\"";
							}
							echo "<li".$active." id=\"tab_".$group_slug."\"><a href=\"#" . $group_slug . "-config-panel\">" . $tab_setup['name'] . "</a></li>\r\n";
						}

						// CODE BASED TABS
						if(!empty($panel['tabs']['code'])){
							foreach($panel['tabs']['code'] as $code_slug=>$tab_setup){
								$active = null;
								if(!empty($tab_setup['active'])){
									$active = " class=\"active\"";
								}
								echo "<li".$active."><a href=\"#" . $code_slug . "-code-panel\" data-editor=\"" . $code_slug . "-editor\">" . $tab_setup['name'] . "</a></li>\r\n";
							}
						}

					}

					?>
			<?php
		}
		?>
		</ul>
	</div>
<?php

// PANEL WRAPPERS & RENDER
$repeatable_templates = array();
foreach($panel_extensions as $panel){
	if(empty($panel['tabs'])){
		continue;
	}

	foreach($panel['tabs'] as $panel_slug=>$tab_setup){
		$active = "  style=\"display:none;\"";
		if(!empty($tab_setup['active'])){
			$active = null;
		}
		echo "<div id=\"" . $panel_slug . "-config-panel\" class=\"caldera-editor-body caldera-config-editor-panel " . ( !empty($tab_setup['side_panel']) ? "caldera-config-has-side" : "" ) . "\"".$active.">\r\n";
			if( !empty($tab_setup['side_panel']) ){
				echo "<div id=\"" . $panel_slug . "-config-panel-main\" class=\"caldera-config-editor-main-panel\">\r\n";
			}
			echo '<h3>'.$tab_setup['label'];
				if( !empty( $tab_setup['repeat'] ) ){
					// add a repeater button
					echo " <a href=\"#" . $panel_slug . "_tag\" class=\"add-new-h2 caldera-add-group\" data-group=\"" . $panel_slug . "\">" . esc_html__( 'Add New', 'caldera-forms' ) . "</a>\r\n";
				}
				// ADD ACTIONS
				if(!empty($tab_setup['actions'])){
					foreach($tab_setup['actions'] as $action){
						include $action;
					}
				}
			echo '</h3>';
			// BUILD CONFIG FIELDS
			if(!empty($tab_setup['fields'])){
				// group index for loops
				$depth = 1;
				if(isset($element['settings'][$panel_slug])){
					// find max depth
					foreach($element['settings'][$panel_slug] as &$field_vars){
						if(count($field_vars) > $depth){
							$depth = count($field_vars);
						}
					}
				}
				for($group_index = 0; $group_index < $depth; $group_index++){
					
					if( !empty( $tab_setup['repeat'] ) ){
						echo "<div class=\"caldera-config-editor-panel-group\">\r\n";
					}
					foreach($tab_setup['fields'] as $field_slug=>&$field){
						$wrapper_before = "<div class=\"caldera-config-group\">";
						$field_before = "<div class=\"caldera-config-field\">";
						$field_after = '</div>';
						$wrapper_after = '</div>';
						$field_name = 'config[settings][' . $panel_slug . '][' . $field_slug . ']';
						$field_base_id = $field_id = $panel_slug. '_' . $field_slug . '_' . $group_index;						
						$field_label = "<label for=\"" . $field_id . "\">" . $field['label'] . "</label>\r\n";
						$field_placeholder = "";
						$field_required = "";
						if(!empty($field['hide_label'])){
							$field_label = "";
							$field_placeholder = 'placeholder="' . htmlentities( $field['label'] ) .'"';
						}


						$field_caption = null;
						if(!empty($field['caption'])){
							$field_caption = "<p class=\"description\">" . $field['caption'] . "</p>\r\n";
						}

						// blank default
						$field_value = null;

						if(isset($field['config']['default'])){
							$field_value = $field['config']['default'];
						}
						if(isset($element['settings'][$panel_slug][$field_slug])){
							$field_value = $element['settings'][$panel_slug][$field_slug];
						}

						$field_class = "field-config";
						if(!empty($field['required'])){
							$field_class .= " required";							
						}
						include $field_types[$field['type']]['file'];

					}
					if( !empty( $tab_setup['repeat'] ) ){
						echo "<a href=\"#remove_" . $panel_slug . "\" class=\"caldera-config-group-remove\">" . esc_html__( 'Remove', 'caldera-forms' ) . "</a>\r\n";
						echo "</div>\r\n";
					}
				}


				/// CHECK GROUP IS REPEATABLE ADN ADD A TEMPLATE IF IT IS
				if( !empty( $tab_setup['repeat'] ) ){

					$field_template = "<script type=\"text/html\" id=\"" . $panel_slug . "_panel_tmpl\">\r\n";
					$field_template .= "	<div class=\"caldera-config-editor-panel-group\">\r\n";

					foreach($tab_setup['fields'] as $field_slug=>&$field){
						
						$field_name = 'config[settings][' . $panel_slug . '][' . $field_slug . '][]';
						$field_id = $panel_slug. '_' . $field_slug;

						// blank default
						$field_value = null;

						if(isset($field['config']['default'])){
							$field_value = $field['config']['default'];
						}

						$field_template .= "	<div class=\"caldera-config-group\">\r\n";
							$field_template .= "		<label for=\"" . $field_id . "\">" . $field['label'] . "</label>\r\n";
							$field_template .= "		<div class=\"caldera-config-field\">\r\n";
								ob_start();
								include $field_types[$field['type']]['file'];
								$field_template .= ob_get_clean();
							$field_template .= "		</div>\r\n";
						$field_template .= "	</div>\r\n";

					}
					$field_template .= "	<a href=\"#remove-group\" class=\"caldera-config-group-remove\">" . esc_html__( 'Remove', 'caldera-forms' ) . "</a>\r\n";
					$field_template .= "	</div>\r\n";
					$field_template .= "</script>\r\n";

					$repeatable_templates[] = $field_template;

				}


			}elseif(!empty($tab_setup['canvas'])){
				include $tab_setup['canvas'];
			}

			if(!empty($tab_setup['side_panel'])){
				echo "</div>\r\n";
				echo "<div id=\"" . $panel_slug . "-config-panel-side\" class=\"caldera-config-editor-side-panel\">\r\n";

					include $tab_setup['side_panel'];

				echo "</div>\r\n";
			}

		echo "</div>\r\n";
	}
	echo "<a name=\"" . $panel_slug . "_tag\"></a>";
}

// PROCESSORS
do_action('caldera_forms_edit_end', $element);
?>
<script type="text/html" id="field-options-cofnig-tmpl">
<?php
	echo $field_options_template;
?>
</script>

<script type="text/html" id="form-fields-selector-tmpl">
	<div class="modal-tab-panel">
	<?php

		
		$sorted_field_types = array(
			__( 'Basic', 'caldera-forms' ) => '',
			__( 'Select', 'caldera-forms' ) => '',
			__( 'File', 'caldera-forms' ) => '',
			__( 'Content', 'caldera-forms' ) => '',
			__( 'Special', 'caldera-forms' ) => '',
			
		);

		if( defined( 'CFCORE_SHOW_DISCONTINUED_FIELDS' ) && CFCORE_SHOW_DISCONTINUED_FIELDS  ){
			$sorted_field_types[ __( 'Discontinued', 'caldera-forms' ) ] = '';
		}

		foreach($field_types as $field_slug=>$config){
			$cats[] = 'General';
			if(!empty($config['category'])){
				$cats = explode(',', $config['category']);
			}

			$icon = CFCORE_URL . "assets/images/field.png";
			if(!empty($config['icon'])){
				$icon = $config['icon'];
			}
			foreach($cats as $cat){
				$cat = trim($cat);
				if(  __( 'Discontinued', 'caldera-forms' ) == $cat ){
					continue;
				}
				$template = '<div class="form-modal-add-line">';
					$template .= '<button type="button" class="button info-button set-current-field" data-field="{{id}}" data-type="' . $field_slug . '">' . esc_html__( 'Set Field', 'caldera-forms' ) . '</button>';
					$template .= '<img src="'. $icon .'" class="form-modal-lgo" width="45" height="45">';
					$template .= '<strong>' . $config['field'] . '</strong>';
					$template .= '<p class="description">' . (!empty($config['description']) ? $config['description'] : esc_html__( 'No description given', 'caldera-forms' ) ) . '</p>';
				$template .= '</div>';
				if(!isset($sorted_field_types[$cat])){
					$cat = __( 'Special', 'caldera-forms' );
				}
				$sorted_field_types[$cat] .= $template;
			}
		}

		$cat_show = false;

		foreach($sorted_field_types as $cat=>$template){
			if(!empty($cat_show)){
				$cat_show = 'style="display: none;"';
			}
			echo '<div id="modal-category-'. sanitize_key( $cat ) .'" data-tab="' . esc_attr( $cat ) . '" class="tab-detail-panel" '.$cat_show.'>';
				echo $template;
			echo '</div>';
			$cat_show = true;
		}

	?>
	</div>
</script>
<script type="text/html" id="caldera_field_config_wrapper_templ">
<?php
	echo field_wrapper_template();
?>
</script>
<script type="text/html" id="field-option-row-tmpl">
	{{#each option}}
	<div class="toggle_option_row">
		<i class="dashicons dashicons-sort" style="padding: 4px 9px;"></i>
		<input type="radio" class="toggle_set_default field-config" name="{{../_name}}[default]" value="{{@key}}" {{#is ../default value="@key"}}checked="checked"{{/is}}>
		<span style="position: relative; display: inline-block;"><input type="text" class="toggle_value_field field-config magic-tag-enabled" name="{{../_name}}[option][{{@key}}][value]" value="{{value}}" placeholder="value"></span>
		<input type="text" class="toggle_label_field field-config" data-option="{{@key}}"  name="{{../_name}}[option][{{@key}}][label]" value="{{label}}" placeholder="label">
		<button class="button button-small toggle-remove-option" type="button"><i class="icn-delete"></i></button>		
	</div>
	{{/each}}
</script>
<script type="text/html" id="noconfig_field_templ" class="cf-editor-template">
<div class="caldera-config-group">
	<label>Default</label>
	<div class="caldera-config-field">
		<input type="text" class="block-input field-config" name="{{_name}}[default]" value="{{default}}">
	</div>
</div>
</script>
<script type="text/html" id="conditional-group-tmpl">	
	{{#each group}}
		<div class="caldera-condition-group">
			<div class="caldera-condition-group-label"><?php echo esc_html__( 'or', 'caldera-forms' ); ?></div>			
			<div class="caldera-condition-lines" id="{{id}}_conditions_lines">
				{{#each lines}}
				<div class="caldera-condition-line">
					if 
					<select name="config[{{../type}}][{{../../id}}][conditions][group][{{../id}}][{{id}}][field]" data-condition="{{../type}}" class="caldera-field-bind caldera-conditional-field-set" data-id="{{../../id}}" {{#if field}}data-default="{{field}}"{{/if}} data-line="{{id}}" data-row="{{../id}}" data-all="true" style="max-width:120px;">
						{{#if field}}<option value="{{field}}" class="bound-field" selected="selected"></option>{{else}}<option value="">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</option>{{/if}}
					</select>
					<select class="compare-type" name="config[{{../type}}][{{../../id}}][conditions][group][{{../id}}][{{id}}][compare]" style="max-width:110px;">
						<option value="is" {{#is compare value="is"}}selected="selected"{{/is}}><?php echo esc_html__( 'is', 'caldera-forms' ); ?></option>
						<option value="isnot" {{#is compare value="isnot"}}selected="selected"{{/is}}><?php echo esc_html__( 'is not', 'caldera-forms' ); ?></option>
						<option value=">" {{#is compare value=">"}}selected="selected"{{/is}}><?php echo esc_html__( 'is greater than', 'caldera-forms' ); ?></option>
						<option value="<" {{#is compare value="<"}}selected="selected"{{/is}}><?php echo esc_html__( 'is less than', 'caldera-forms' ); ?></option>
						<option value="startswith" {{#is compare value="startswith"}}selected="selected"{{/is}}><?php echo esc_html__( 'starts with', 'caldera-forms' ); ?></option>
						<option value="endswith" {{#is compare value="endswith"}}selected="selected"{{/is}}><?php echo esc_html__( 'ends with', 'caldera-forms' ); ?></option>
						<option value="contains" {{#is compare value="contains"}}selected="selected"{{/is}}><?php echo esc_html__( 'contains', 'caldera-forms' ); ?></option>
					</select>
					<span style="padding: 0 12px 0; " class="caldera-conditional-field-value" data-value="{{value}}" id="{{id}}_value"><input disabled type="text" value="" placeholder="<?php echo esc_html__( 'Select field first', 'caldera-forms' ); ?>" style="max-width: 165px;"></span>
					<button type="button" class="button remove-conditional-line pull-right"><i class="icon-join"></i></button>
				</div>
				{{/each}}
			</div>
			<button type="button" class="button button-small ajax-trigger" data-id="{{../id}}" data-type="{{type}}" data-group="{{id}}" data-request="new_conditional_line" data-target="#{{id}}_conditions_lines" data-callback="rebuild_field_binding" data-template="#conditional-line-tmpl" data-target-insert="append"><?php echo esc_html__( 'Add Condition', 'caldera-forms' ); ?></button>
		</div>
	{{/each}}
</script>
<script type="text/html" id="conditional-line-tmpl">
	<div class="caldera-condition-line">
		<div class="caldera-condition-line-label"><?php echo esc_html__( 'and', 'caldera-forms' ); ?></div>
		if 
		<select name="{{name}}[field]" class="caldera-field-bind caldera-conditional-field-set" data-condition="{{type}}" data-id="{{id}}" data-line="{{lineid}}" data-row="{{rowid}}" data-all="true" style="max-width:120px;"></select>
		<select name="{{name}}[compare]" style="max-width:110px;">
			<option value="is"><?php echo esc_html__( 'is', 'caldera-forms' ); ?></option>
			<option value="isnot"><?php echo esc_html__( 'is not', 'caldera-forms' ); ?></option>
			<option value=">"><?php echo esc_html__( 'is greater than', 'caldera-forms' ); ?></option>
			<option value="<"><?php echo esc_html__( 'is less than', 'caldera-forms' ); ?></option>
			<option value="startswith"><?php echo esc_html__( 'starts with', 'caldera-forms' ); ?></option>
			<option value="endswith"><?php echo esc_html__( 'ends with', 'caldera-forms' ); ?></option>
			<option value="contains"><?php echo esc_html__( 'contains', 'caldera-forms' ); ?></option>
		</select>
		<span class="caldera-conditional-field-value" id="{{lineid}}_value"><input disabled type="text" value="" placeholder="<?php echo esc_html__( 'Select field first', 'caldera-forms' ); ?>" style="max-width: 165px;"></span>
		<button type="button" class="button remove-conditional-line pull-right"><i class="icon-join"></i></button>
	</div>
</script>
<?php

/// Output the field templates
foreach($field_type_templates as $key=>$template){
	echo "<script type=\"text/html\" class=\"cf-editor-template\" id=\"" . $key . "\">\r\n";
		echo $template;
	echo "\r\n</script>\r\n";
}
?>

<?php


$magic_script = array(
	'field' => array()
);

foreach($magic_tags as $magic_set_key=>$magic_tags_set){

	$magic_script[$magic_set_key] = array(
		'type'	=>	$magic_tags_set['type'],
		'tags'	=>	array(),
		'wrap'	=>  $magic_tags_set['wrap']
	);

	foreach($magic_tags_set['tags'] as $tag_key=>$tag_value){

		if(is_array($tag_value)){
			foreach($tag_value as $compatibility){
				$magic_script[$magic_set_key]['tags'][$compatibility][] = $tag_key;
			}
		}else{
			$magic_script[$magic_set_key]['tags']['text'][] = $tag_value;
		}
	}

}

?>
<script type="text/javascript">

<?php
// output fieldtype defaults
echo implode("\r\n", $field_type_defaults);

?>
var system_values = <?php echo json_encode( $magic_script ); ?>;

</script>





































































