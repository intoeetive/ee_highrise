<?=form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=ee_highrise'.AMP.'method=save_settings');?>

<?php 
$this->table->set_template($cp_pad_table_template);


foreach ($settings as $name => $data)
{
	echo "<h3 class=\"accordion\">".lang($name)."</h3>".BR;
	switch ($name)
	{
		case 'member_groups':
			$this->table->set_heading(
			    array('data' => lang('ee_group'), 'style' => 'width:50%;'),
			    lang('highrise_tag')
			);
			break;
		case 'profile_fields':
			$this->table->set_heading(
			    array('data' => lang('highrise_field'), 'style' => 'width:50%;'),
			    lang('ee_field')
			);
			break;
		default:
			$this->table->set_heading(
			    array('data' => lang('preference'), 'style' => 'width:50%;'),
			    lang('setting')
			);
			break;
	}

	foreach ($data as $key => $val)
	{
		$this->table->add_row(lang($key, $key), $val);
	}
	
	echo $this->table->generate();
}



?>


<p><?=form_submit('submit', lang('save'), 'class="submit"')?></p>

<?php
form_close();