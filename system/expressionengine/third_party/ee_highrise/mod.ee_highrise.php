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
 File: mod.ee_highrise.php
-----------------------------------------------------
 Purpose: Integration with Highrise CRM
=====================================================
*/

if ( ! defined('BASEPATH'))
{
	exit('Invalid file request');
}

require_once PATH_THIRD.'ee_highrise/config.php';


class Ee_highrise {

    var $return_data	= ''; 						// Bah!
    
    var $settings 		= array();  

    function __construct()
    {        
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
	
	function sync_me()
	{
		$this->EE->load->library('highrise_lib');
		$this->EE->highrise_lib->sync($this->EE->session->userdata('member_id'));
	}
	
	function sync_all()
    {
    	
    	$settings = $this->_get_settings();
    	
    	$member_fields = array();
    	$required_fields = array();
        $this->EE->db->select('m_field_id, m_field_name, m_field_required');
        $q = $this->EE->db->get('member_fields');		
		if ($q->num_rows() > 0)
		{
			foreach ($q->result() as $obj)
	        {
	            $member_fields['m_field_id_'.$obj->m_field_id] = $obj->m_field_name;
	            if ($obj->m_field_required=='y')
	            {
	            	$required_fields[$obj->m_field_id] = $obj->m_field_name;
	            }
	        }
		}
    	
    	$this->EE->load->library('highrise_lib');
    	
    	//take groups, one by one, in priority order
    	$groups_priorities = array();
        $this->EE->db->select('group_id');
        $q = $this->EE->db->get('member_groups');
        foreach ($q->result() as $obj)
        {
            if ($settings['remote_group_'.$obj->group_id]!='')
			{
				$groups_priorities[$obj->group_id] = (isset($settings['priority_'.$obj->group_id]))?$settings['priority_'.$obj->group_id]:0;
			}
        }
        //lowest proirity goes first, so that highest priority could overwrite data
        ksort($groups_priorities);
        //var_dump($groups_priorities);
    	
    	echo "Sync started...".BR.BR;
    	
    	foreach ($groups_priorities as $group_id=>$priority)
    	{
	    	$q = $this->EE->db->select('member_id, screen_name')->from('members')->where('group_id', $group_id)->get();
	    	foreach ($q->result() as $obj)
	    	{
	    		//var_dump($obj->member_id);
	    		echo $obj->screen_name.BR;
	    		$this->EE->highrise_lib->sync($obj->member_id, $group_id, false, $settings, $member_fields, $required_fields);
	   		}
    	}
    	
    	echo BR."Complete";
    	
    }


}
?>