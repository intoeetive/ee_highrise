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
 File: upd.ee_highrise.php
-----------------------------------------------------
 Purpose: Integration with Highrise CRM
=====================================================
*/

if ( ! defined('BASEPATH'))
{
	exit('Invalid file request');
}

require_once PATH_THIRD.'ee_highrise/config.php';


class Ee_highrise_upd {

    var $version = EE_HIGHRISE_ADDON_VERSION;
    
    function __construct() { 
        // Make a local reference to the ExpressionEngine super object 
        $this->EE =& get_instance(); 
    } 
    
    function install() 
	{ 
        
		$this->EE->load->dbforge();         
        /*
		//----------------------------------------
		// EXP_MODULES
		// The settings column, Ellislab should have put this one in long ago.
		// No need for a seperate preferences table for each module.
		//----------------------------------------
		if ($this->EE->db->field_exists('settings', 'modules') == FALSE)
		{
			$this->EE->dbforge->add_column('modules', array('settings' => array('type' => 'TEXT') ) );
		}     
		
		//----------------------------------------
		// Add fields to exp_comments table
		//---------------------------------------- 	 
		if ($this->EE->db->field_exists('data_type', 'members') == FALSE)
		{
			$this->EE->dbforge->add_column('members', array('highrise_sync_date' => array('type' => 'INT', 'default' => '0')));
		}	

        */

		$module = array(	'module_name' => 'Ee_highrise',
							'module_version' => $this->version,
							'has_cp_backend' => 'y',
							'has_publish_fields' => 'n');

		$this->EE->db->insert('modules', $module); 
        
        $module = array('class' => 'Ee_highrise', 'method' => 'sync_all' );
		$this->EE->db->insert('actions', $module);   
        
        return TRUE; 
        
    } 
    
    
    function uninstall() { 
        
        $this->EE->load->dbforge(); 
        
        $this->EE->db->select('module_id'); 
        $query = $this->EE->db->get_where('modules', array('module_name' => 'Ee_highrise')); 
        
        $this->EE->db->where('module_id', $query->row('module_id')); 
        $this->EE->db->delete('module_member_groups'); 
        
        $this->EE->db->where('module_name', 'Ee_highrise'); 
        $this->EE->db->delete('modules'); 
        
        $this->EE->db->where('class', 'Ee_highrise'); 
        $this->EE->db->delete('actions'); 
        
        return TRUE; 
    } 
    
    function update($current='') { 
        if ($current < 2.2) 
        { 

        } 
        return TRUE; 
    } 
	

}
/* END */
?>