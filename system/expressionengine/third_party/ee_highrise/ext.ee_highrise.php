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
 File: ext.ee_highrise.php
-----------------------------------------------------
 Purpose: Integration with Highrise CRM
=====================================================
*/

if ( ! defined('BASEPATH'))
{
	exit('Invalid file request');
}

require_once PATH_THIRD.'ee_highrise/config.php';

class Ee_highrise_ext {

	var $name	     	= EE_HIGHRISE_ADDON_NAME;
	var $version 		= EE_HIGHRISE_ADDON_VERSION;
	var $description	= 'Integration with Highrise CRM';
	var $settings_exist	= 'y';
	var $docs_url		= 'http://www.intoeetive.com/docs/ee_highrise.html';
    
    var $settings 		= array();
    
	/**
	 * Constructor
	 *
	 * @param 	mixed	Settings array or empty string if none exist.
	 */
	function __construct($settings = '')
	{
		$this->EE =& get_instance();
        $this->settings = $settings;
	}
    
    /**
     * Activate Extension
     */
    function activate_extension()
    {
        
        $hooks = array(
    		array(
    			'hook'		=> 'member_member_register',
    			'method'	=> 'member_member_register',
    			'priority'	=> 10
    		),
    		array(
    			'hook'		=> 'cp_members_member_create',
    			'method'	=> 'cp_members_member_create',
    			'priority'	=> 10
    		),
    		array(
    			'hook'		=> 'user_register_end',
    			'method'	=> 'user_register_end',
    			'priority'	=> 10
    		),
    		array(
    			'hook'		=> 'membrr_subscribe',
    			'method'	=> 'membrr_subscribe',
    			'priority'	=> 10
    		),
    		array(
    			'hook'		=> 'membrr_cancel',
    			'method'	=> 'membrr_cancel',
    			'priority'	=> 10
    		),
    		array(
    			'hook'		=> 'membrr_expire',
    			'method'	=> 'membrr_expire',
    			'priority'	=> 10
    		),
    		array(
    			'hook'		=> 'user_edit_end',
    			'method'	=> 'user_edit_end',
    			'priority'	=> 10
    		),
    		array(
    			'hook'		=> 'cp_members_member_delete_end',
    			'method'	=> 'cp_members_member_delete_end',
    			'priority'	=> 10
    		)
    		
    		
    		
    	);
    	
        foreach ($hooks AS $hook)
    	{
    		$data = array(
        		'class'		=> __CLASS__,
        		'method'	=> $hook['method'],
        		'hook'		=> $hook['hook'],
        		'settings'	=> '',
        		'priority'	=> $hook['priority'],
        		'version'	=> $this->version,
        		'enabled'	=> 'y'
        	);
            $this->EE->db->insert('extensions', $data);
    	}	        

    }
    
    /**
     * Update Extension
     */
    function update_extension($current = '')
    {
    	if ($current == '' OR $current == $this->version)
    	{
    		return FALSE;
    	}
    }
    
    
    /**
     * Disable Extension
     */
    function disable_extension()
    {
    	$this->EE->db->where('class', __CLASS__);
    	$this->EE->db->delete('extensions');
    }
        
    
    function settings_form($current)
    {
    	$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=ee_highrise'.AMP.'method=settings');			
    }
    
    function member_member_register($data, $member_id)
    {
    	$this->sync($member_id, $data['group_id'], true);
    }
    
    function cp_members_member_create($member_id, $data)
    {
    	$this->sync($member_id, $data['group_id'], true);
    }
    
    function user_register_end($obj, $member_id)
    {
    	$q = $this->EE->db->select('group_id')->from('members')->where('member_id', $member_id)->get();
		$this->sync($member_id, $q->row('group_id'), true);
    }
    
    function membrr_subscribe($member_id, $recurring_id, $planid, $end_date)
    {
    	$this->sync($member_id, $this->EE->session->userdata('group_id'));
    }
    
    function membrr_cancel($member_id, $subscriptionid, $plan_id, $end_date)
    {
    	$this->sync($member_id, $this->EE->session->userdata('group_id'));
    }
    
    function membrr_expire($member_id, $recurring_id, $plan_id)
    {
    	$q = $this->EE->db->select('group_id')->from('members')->where('member_id', $member_id)->get();
		$this->sync($member_id, $q->row('group_id'));
    }
    
    function user_edit_end($member_id, $data, $cfields)
    {
    	$this->sync($member_id, $this->EE->session->userdata('group_id'));
    }
    
    function cp_members_member_delete_end()
    {
    	foreach ($_POST['delete'] as $key => $val)
		{		
			if ($val != '')
			{
				$this->sync($val);
			}		
		}
    }
    
    
    
    
    
    
    
    
    function sync($member_id, $new_member=false)
    {
    	
    	$this->EE->load->library('highrise_lib');
    	
    	$this->EE->highrise_lib->sync($member_id, $new_member);
    	
    	
    }

    
    
    
    
    

}
// END CLASS
