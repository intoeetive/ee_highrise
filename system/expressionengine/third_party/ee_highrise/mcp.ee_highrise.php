<?php

/*
=====================================================
 EE Highrise
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2012 Yuri Salimovskiy
=====================================================
 This software is intended for usage with
 ExpressionEngine CMS, version 2.0 or higher
=====================================================
 File: mcp.ee_highrise.php
-----------------------------------------------------
 Purpose: Integration with Highrise CRM
=====================================================
*/

if ( ! defined('BASEPATH'))
{
	exit('Invalid file request');
}

require_once PATH_THIRD.'ee_highrise/config.php';


class Ee_highrise_mcp {

    var $version = EE_HIGHRISE_ADDON_VERSION;
    
    var $settings = array();
    
    var $perpage = 50;

    function __construct() 
    { 
        // Make a local reference to the ExpressionEngine super object 
        $this->EE =& get_instance(); 
    } 
    
    function _get_settings()
    {
    	$query = $this->EE->db->select('settings')->from('modules')->where('module_name', 'Ee_highrise')->limit(1)->get();
        $settings = unserialize($query->row('settings')); 
        if (!empty($settings))
        {
        	return $settings;
        }
        else
        {
        	return array();
        }
        
    }
    
    
    function _curl_init($api_key)
    {
    	$this->EE->load->library('curl');
		
		$this->EE->curl->http_header('Content-Type', 'application/xml');
		$this->EE->curl->http_header('Accept', 'application/xml');
		$this->EE->curl->http_header("Authorization", "Basic ".base64_encode($api_key));
 		$this->EE->curl->option('HTTPAUTH', CURLAUTH_BASIC);
 		$this->EE->curl->option('SSLVERSION', 3);
		$this->EE->curl->option('SSL_VERIFYPEER', FALSE);
		$this->EE->curl->option('SSL_VERIFYHOST', FALSE);
    }
    
    
    
    
    
    

    function index()
    {
        return $this->settings();
    }    



    function settings()
    {
        $this->EE->load->helper('form');
    	$this->EE->load->library('table');
        
        $settings = $this->_get_settings();
        
        $member_fields = array();
        $member_fields_by_id = array();
        $this->EE->db->select('m_field_id, m_field_name, m_field_label');
        $q = $this->EE->db->get('member_fields');	
        $member_fields[''] = '';	
        $member_fields['member_id'] = lang('member_id');
        $member_fields['email'] = lang('email');
        $member_fields['username'] = lang('username');
        $member_fields['group_title'] = lang('group_name');
        
        $member_fields_by_id[''] = '';
        //$member_fields['city_for_company_name'] = lang('city_for_company_name');
		if ($q->num_rows() > 0)
		{
			foreach ($q->result() as $obj)
	        {
	            $member_fields[$obj->m_field_name] = $obj->m_field_label;
	            $member_fields_by_id[$obj->m_field_id] = $obj->m_field_label;
	        }
		}
		
		
		
		
		
		$highrise_groups = array();
		$highrise_groups[''] = lang('do_not_sync');
		$highrise_fields = array();
		$highrise_fields['first-name'] = lang('first-name');
		$highrise_fields['last-name'] = lang('last-name');
		//$highrise_fields['title'] = lang('title');
		$highrise_fields['company-name'] = lang('company-name');
		//$highrise_fields['background'] = lang('background');
		$highrise_fields['email-work'] = lang('email-work');
		$highrise_fields['email-home'] = lang('email-home');
		$highrise_fields['email-other'] = lang('email-other');
		$highrise_fields['phone-work'] = lang('phone-work');
		$highrise_fields['phone-home'] = lang('phone-home');
		/*$highrise_fields['phone-mobile'] = lang('phone-mobile');
		$highrise_fields['phone-fax'] = lang('phone-fax');
		$highrise_fields['phone-pager'] = lang('phone-pager');
		$highrise_fields['phone-skype'] = lang('phone-skype');*/
		$highrise_fields['phone-other'] = lang('phone-other');
		$highrise_fields['city-work'] = lang('city-work');
		$highrise_fields['country-work'] = lang('country-work');
		$highrise_fields['state-work'] = lang('state-work');
		$highrise_fields['street-work'] = lang('street-work');
		$highrise_fields['zip-work'] = lang('zip-work');
		/*$highrise_fields['city-home'] = lang('city-home');
		$highrise_fields['country-home'] = lang('country-home');
		$highrise_fields['state-home'] = lang('state-home');
		$highrise_fields['street-home'] = lang('street-home');
		$highrise_fields['zip-home'] = lang('zip-home');
		$highrise_fields['city-other'] = lang('city-other');
		$highrise_fields['country-other'] = lang('country-other');
		$highrise_fields['state-other'] = lang('state-other');
		$highrise_fields['street-other'] = lang('street-other');
		$highrise_fields['zip-other'] = lang('zip-other');
		$highrise_fields['im-aim'] = lang('im-aim');
		$highrise_fields['im-msn'] = lang('im-msn');
		$highrise_fields['im-icq'] = lang('im-icq');
		$highrise_fields['im-jabber'] = lang('im-jabber');
		$highrise_fields['im-yahoo'] = lang('im-yahoo');
		$highrise_fields['im-skype'] = lang('im-skype');
		$highrise_fields['im-qq'] = lang('im-qq');
		$highrise_fields['im-sametime'] = lang('im-sametime');
		$highrise_fields['im-gadugadu'] = lang('im-gadugadu');
		$highrise_fields['im-gtalk'] = lang('im-gtalk');
		$highrise_fields['im-other'] = lang('im-other');
		$highrise_fields['twitter'] = lang('twitter');*/
		$highrise_fields['url'] = lang('url');
		if (isset($settings['highrise_account']) && isset($settings['highrise_api_key']) && $settings['highrise_account']!='' && $settings['highrise_api_key']!='')
		{
			$this->_curl_init($settings['highrise_api_key']);
			$result = $this->EE->curl->simple_get("https://" . $settings['highrise_account'] . ".highrisehq.com/tags.xml");
	 		if ($result==false) 
	 		{
	 			exit('false');
	 		}
			
			$xml = new SimpleXMLElement($result);

			foreach ($xml->tag as $tag)
			{
				$highrise_groups[(string)$tag->name] = $tag->name;
			}
			
			
			$this->_curl_init($settings['highrise_api_key']);
			$result = $this->EE->curl->simple_get("https://" . $settings['highrise_account'] . ".highrisehq.com/subject_fields.xml");
	 		if ($result==false) 
	 		{
	 			exit('false');
	 		}
			
			$xml = new SimpleXMLElement($result);
			$key = "subject-field";
			foreach ($xml->$key as $field)
			{
				$highrise_fields[(int)$field->id] = (string)$field->label;
			}
		}
		
		
		
		
		
        
        $vars = array();
        $vars['settings'] = array();
        $act = $this->EE->db->select('action_id')->from('actions')->where('class', 'Ee_highrise')->where('method', 'sync_all')->get();
        $vars['settings']['general']['sync_url']	= $this->EE->config->item('site_url')."?ACT=".$act->row('action_id');
        $vars['settings']['general']['highrise_account']	= form_input('highrise_account', isset($settings['highrise_account'])?$settings['highrise_account']:'', ' style="width: 100%"');
        $vars['settings']['general']['highrise_api_key']	= form_input('highrise_api_key', isset($settings['highrise_api_key'])?$settings['highrise_api_key']:'', ' style="width: 100%"');
        $vars['settings']['general']['highrise_person_id_field']	= form_dropdown('highrise_person_id_field', $member_fields, isset($settings['highrise_person_id_field'])?$settings['highrise_person_id_field']:'');
		//$vars['settings']['general']['highrise_company_id_field']	= form_dropdown('highrise_company_id_field', $member_fields, isset($settings['highrise_company_id_field'])?$settings['highrise_company_id_field']:'');	
				

		$member_groups = array();
        $this->EE->db->select('group_id, group_title');
        $this->EE->db->where('site_id', $this->EE->config->item('site_id'));
        $q = $this->EE->db->get('member_groups');
        foreach ($q->result() as $obj)
        {
            $vars['settings']['member_groups'][$obj->group_title]	= form_dropdown('remote_group_'.$obj->group_id, $highrise_groups, isset($settings['remote_group_'.$obj->group_id])?$settings['remote_group_'.$obj->group_id]:'');
            $member_groups[$obj->group_id] = $obj->group_title;
        }
        $vars['settings']['member_groups']['deleted_member']	= form_dropdown('remote_group_0', $highrise_groups, isset($settings['remote_group_0'])?$settings['remote_group_0']:'');
        

        foreach ($member_groups as $group_id=>$group_title)
        {
        	$vars['settings']['groups_priority'][$group_title]	= form_input('priority_'.$group_id, isset($settings['priority_'.$group_id])?$settings['priority_'.$group_id]:'0');
       	}
       	
       	foreach ($member_groups as $group_id=>$group_title)
        {
        	$vars['settings']['member_permalink_prefix'][$group_title]	= form_input('permalink_prefix_'.$group_id, isset($settings['permalink_prefix_'.$group_id])?$settings['permalink_prefix_'.$group_id]:'');
       	}

        
		//$vars['settings']['profile_fields'][lang('email')] = form_dropdown('profile_field_email', $highrise_fields, isset($settings['profile_field_email'])?$settings['profile_field_email']:'');	
		//unset($member_fields['']);	
		//$vars['settings']['profile_fields'][lang('member_id')] = form_dropdown('profile_field_member_id', $highrise_fields, isset($settings['profile_field_member_id'])?$settings['profile_field_member_id']:'');
		foreach ($highrise_fields as $field_id=>$field_label)
        {
			$vars['settings']['profile_fields'][$field_label]	= form_dropdown('remote_field_'.$field_id, $member_fields, isset($settings['remote_field_'.$field_id])?$settings['remote_field_'.$field_id]:'');
        }
        
        
        $this->EE->cp->set_variable('cp_page_title', lang('ee_highrise_module_name'));
        
        /*$this->EE->cp->set_right_nav(array(
		            'sync' => BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=ee_highrise'.AMP.'method=sync_all')
		        );*/
 
    	return $this->EE->load->view('settings', $vars, TRUE);
	
    }    
    
    function save_settings()
    {
        
        if (empty($_POST))
    	{
    		show_error($this->EE->lang->line('unauthorized_access'));
    	}
    	
    	unset($_POST['submit']);
    	$settings = array();
    	foreach ($_POST as $key=>$val)
    	{
    		$settings[$key] = $this->EE->input->post($key);
    	}
    	
    	$this->EE->db->where('module_name', 'Ee_highrise');
        $this->EE->db->update('modules', array('settings' => serialize($settings)));

    	$this->EE->session->set_flashdata(
    		'message_success',
    	 	$this->EE->lang->line('preferences_updated')
    	);
        
        $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=ee_highrise'.AMP.'method=settings');
    }


}
/* END */
?>