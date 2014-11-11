<?php

/*
=====================================================
 Highrise library for EE Highrise
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2012 Yuri Salimovskiy
=====================================================
*/

if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}


class Highrise_lib {
	
	var $ignore_with_field = array(
		'test_member' => 1
	);
	
	//var $url_prefix = "http://www.chimneys.com/chimney-sweep/";
	
	function __construct() { 
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
	
	
	function sync($member_id, $group_id, $new_member=false, $settings=false, $member_fields=false, $required_fields=false)
	{
		if ($member_id=='')
		{
			return false;
		}
		
		if ($settings==false) $settings = $this->_get_settings();
    	//var_dump($settings);
    	if (!isset($settings['highrise_account']) || !isset($settings['highrise_api_key']) || !isset($settings['highrise_person_id_field']) || $settings['highrise_account']=='' || $settings['highrise_api_key']=='' || $settings['highrise_person_id_field']=='')
    	{
    		return false;
    	}
    	//echo "-4";
    	//is in groups we do not sync?
    	if ($settings['remote_group_'.$group_id]=='')
    	{
    		return false;
    	}
    	//echo "-3";
    	
    	//get member fields
    	if ($member_fields==false)
    	{
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
		}
    	//echo "-2";
    	//get member data
    	$this->EE->db->from('members');
    	$sql_what = 'members.member_id, username, email';
    	if (count($member_fields)>0)
		{
			foreach ($member_fields as $field_tech_name => $alias)
			{
				$sql_what .= ', '.$field_tech_name.' AS '.$alias;
			}
			$this->EE->db->join('member_data', 'members.member_id=member_data.member_id', 'left');
		}
		$sql_what .= ', group_title';
		$this->EE->db->join('member_groups', 'members.group_id=member_groups.group_id', 'left');
    	$this->EE->db->select($sql_what);
		$this->EE->db->where('members.member_id', $member_id);
		$q = $this->EE->db->get();
		
		if ($q->num_rows()> 0)
		{
	    	//should we just ignore this member?
	    	if (!empty($this->ignore_with_field))
	    	{	
				foreach ($this->ignore_with_field as $field=>$val)
	    		{
	    			if ($q->row($field)==$val)
	    			{
	    				return false;
	    			}
	    		}
			}
			
			//if any of required profile fields is not set, we also ignore
  			if (!empty($required_fields))
	    	{	
				foreach ($required_fields as $field_id=>$field_name)
	    		{
	    			if ($q->row($field_name)=='')
	    			{
	    				return false;
	    			}
	    		}
			}
			
    	}		
    	//echo "-1";
    	
    	
		//get all remote member fields
		$highrise_fields = array(
			
		);
		$this->_curl_init($settings['highrise_api_key']);
		$result = $this->EE->curl->simple_get("https://" . $settings['highrise_account'] . ".highrisehq.com/subject_fields.xml");
 		if ($result==false) 
 		{
 			return false;
 		}
		
		$xml = new SimpleXMLElement($result);
		$key = "subject-field";
		foreach ($xml->$key as $field)
		{
			$highrise_fields[(string)$field->id] = $field->label;
		}
		
		//member id field to search by
		$member_id_field = urlencode(str_replace('remote_field_', '', array_search('member_id', $settings)));
		
		//...and the list of tags
		/*
		$highrise_groups = array();
		$this->_curl_init($settings['highrise_api_key']);
		$result = $this->EE->curl->simple_get("https://" . $settings['highrise_account'] . ".highrisehq.com/tags.xml");
 		if ($result==false) 
 		{
 			return false;
 		}
		
		$xml = new SimpleXMLElement($result);

		foreach ($xml->tag as $tag)
		{
			$highrise_groups[(string)$tag->id] = $tag->name;
		}
		*/
		
		
		
		//member not found? It was a remove request
		if ($q->num_rows()==0)
		{
			
			$this->_curl_init($settings['highrise_api_key']);
			
	    	$result = $this->EE->curl->simple_get("https://" . $settings['highrise_account'] . ".highrisehq.com/people/search.xml?criteria[$member_id_field]=$member_id");
	 		if ($result==false) 
	 		{
	 			return false;
	 		}
			
			$xml = new SimpleXMLElement($result);
			if (!isset($xml->person))
			{
				//there's no record in CRM, so no worries
				return false;
			}
			
			if (is_array($xml->person))
			{
				$deleteme_a = $xml->person;
			}
			else
			{
				$deleteme_a = array($xml->person);
			}
			
			foreach ($deleteme_a as $deleteme)
			{
				//remove group tags
				if (isset($deleteme->tags->tag))
				{
					$remove_tags = array();
					foreach ($settings as $name=>$val)
					{
						if(strpos($name, 'remote_group_')!==false)
						{
							$remove_tags[] = $val;
						}
					}	
					if (is_array($deleteme->tags->tag))
					{
						$existing_tags = $deleteme->tags->tag;
					}
					else
					{
						$existing_tags = array($deleteme->tags->tag);
					}
					foreach ($existing_tags as $existing_tag)
					{
						if (in_array($existing_tag->name, $remove_tags))
						{
							$this->_curl_init($settings['highrise_api_key']);
					    	$this->EE->curl->simple_delete("https://" . $settings['highrise_account'] . ".highrisehq.com/people/".$deleteme->id."/tags/".$existing_tag->id.".xml");
						}
					}
				}
				//add "inactive" tag
				$this->_curl_init($settings['highrise_api_key']);
	  			$this->EE->curl->simple_post("https://" . $settings['highrise_account'] . ".highrisehq.com/people/".$deleteme->id."/tags.xml", "<name>".$settings['remote_group_0']."</name>");
			}
		}
		
		//trying to find out remote ID
    	$new_member = false;
    	$highrise_person_id = false;
    	//echo 'highrise_person_id_field='.$settings['highrise_person_id_field'].BR;
    	if ($q->row($settings['highrise_person_id_field'])!='')
    	{
    		//already have it!
			$highrise_person_id = $q->row($settings['highrise_person_id_field']);
			//echo 'highrise_person_id='.$highrise_person_id.BR;
			//get remote user data
			$this->_curl_init($settings['highrise_api_key']);
			
	    	$result = $this->EE->curl->simple_get("https://" . $settings['highrise_account'] . ".highrisehq.com/people/$highrise_person_id.xml");
	    	//var_dump($result);
	    	//$this->EE->curl->debug();
	 		if ($result!=false) 
	 		{
	 			$xml = new SimpleXMLElement($result);

	 			$highrise_member_data = $xml;
				$highrise_person_id = $highrise_member_data->id;
	 		}
	 		else
	 		{
	 			$was_deleted = true;
	 			$highrise_person_id = false;
	 		}
    	}
    	else if ($new_member==false OR isset($was_deleted))
    	{
    		//try to get by local member id
    		//(only if it's edit, otherwise would be useless call)
    		$this->_curl_init($settings['highrise_api_key']);
	    	$result = $this->EE->curl->simple_get("https://" . $settings['highrise_account'] . ".highrisehq.com/people/search.xml?criteria[$member_id_field]=$member_id");
	 		if ($result!=false) 
	 		{
	 			$xml = new SimpleXMLElement($result);
	 			$highrise_member_data = $xml;
				$highrise_person_id = $highrise_member_data->id;
	 		}
    	}
    	
    	//start building XML for COMPANY
    	$this->EE->load->library('Xml_writer');
		$this->EE->xml_writer->setRootName('company');
		$this->EE->xml_writer->initiate();
		

		$company_name = $q->row($settings['remote_field_company-name']);
		if ($settings['remote_field_city-work']!='' && $q->row($settings['remote_field_city-work'])!='') 
		{
			$company_name .= " - ".$q->row($settings['remote_field_city-work']);
		}
		
		//echo "1";
		//if person exists in CRM, get company info
    	$highrise_company_id = false;
    	$existing_company_fields = array();
    	if ($highrise_person_id!==false)
    	{
    		$key = "company-id";
			$highrise_company_id = $highrise_member_data->$key;
			
			$this->_curl_init($settings['highrise_api_key']);
			
	    	$result = $this->EE->curl->simple_get("https://" . $settings['highrise_account'] . ".highrisehq.com/companies/$highrise_company_id.xml");
	    	if ($result!=false) 
	 		{
	 			$xml = new SimpleXMLElement($result);

				$xml_company = $xml;

			}
  		}
  		//or, try to get company by its name
  		else
  		{
  			//$city_field = urlencode(str_replace('remote_field_', '', array_search('remote_field_city-work', $settings)));
  			//$city_field = $settings['remote_field_city-work'];
  			//var_dump($settings);
  			//var_dump($city_field);
			  
	  		$this->_curl_init($settings['highrise_api_key']);
			
	    	$result = $this->EE->curl->simple_get("https://" . $settings['highrise_account'] . ".highrisehq.com/companies/search.xml?criteria[name]=".urlencode($q->row($settings['remote_field_company-name']))."&criteria[city]=".urlencode($q->row($settings['remote_field_city-work'])));
	    	//echo "https://" . $settings['highrise_account'] . ".highrisehq.com/companies/search.xml?criteria[name]=".urlencode($q->row($settings['remote_field_company-name']))."&criteria[city]=".urlencode($q->row($settings['remote_field_city-work']));
	    	if ($result!=false) 
	 		{
	 			$xml = new SimpleXMLElement($result);
	 			if (isset($xml->company))
	 			{
	 				if (!is_array($xml->company))
	 				{
	 					$arr = array($xml->company);
	 				}
	 				else
	 				{
	 					$arr = $xml->company;
	 				}
	 				foreach ($arr as $data)
	 				{
	 					//we want exact match only!
						 $keys = array('contact-data', 'addresses', 'address', 'city');
						 if (($data->name==$q->row($settings['remote_field_company-name']) || $data->name==$company_name) && $data->$keys[0]->$keys[1]->$keys[2]->$keys[3]==$q->row($settings['remote_field_city-work']))
	 					{
	 						$xml_company = $data;
	 						$highrise_company_id = $data->id;
	 						break;
	 					}
	 				}
 				}
			}
			
			if ($highrise_company_id!=false)
			{
				//if the company exists, we want also check person's name
				//it's possible we're dealing with a duplicate
				$this->_curl_init($settings['highrise_api_key']);
		    	$result = $this->EE->curl->simple_get("https://" . $settings['highrise_account'] . ".highrisehq.com/people/search.xml?criteria[first-name]=".urlencode($q->row($settings['remote_field_first-name']))."&criteria[last-name]=".urlencode($q->row($settings['remote_field_last-name'])));
		 		if ($result!=false) 
		 		{
		 			$xml = new SimpleXMLElement($result);
		 			if (isset($xml->person))
		 			{
		 				if (is_array($xml->person))
						{
							$xml_persons = $xml->person;
						}
						else
						{
							$xml_persons = array($xml->person);
						}
						foreach ($xml_persons as $xml_person)
						{
							//we want exact match of name and company
							$keys = array('first-name', 'last-name', 'company-id');
							if ($xml_person->$keys[0]==$q->row($settings['remote_field_first-name']) && $xml_person->$keys[1]==$q->row($settings['remote_field_last-name']) && $xml_person->$keys[2]==$q->row($highrise_company_id))
							{
								$highrise_member_data = $xml_person;
								$highrise_person_id = $highrise_member_data->id;
							}
						}
						
		 			}
		 		}
			}
			
  		}
  		
  		
  		//echo "2";
  		
  		
  		
  		
  		//add company id node
  		if ($highrise_company_id!=false)
  		{
  			$this->EE->xml_writer->addNode('id', $highrise_company_id, array('type'=>'integer'));
		}
  		
  		//echo "3";
 		if (isset($xml_company)) 
 		{
			//parse existing data into array
			
			//email(s)
			$keys = array('contact-data', 'email-addresses', 'email-address');
			if (isset($xml_company->$keys[0]->$keys[1]->$keys[2]))
			{
				foreach ($xml_company->$keys[0]->$keys[1]->$keys[2] as $data)
				{
					if ($data->location=="Work")
					{
						$existing_company_fields['email-work'] = $data->id;
					}
					if ($data->location=="Other")
					{
						$existing_company_fields['email-other'] = $data->id;
					}
				}
			}
			
			
			//email(s)
			$keys = array('contact-data', 'email-addresses', 'email-address');
			if (isset($xml_company->$keys[0]->$keys[1]->$keys[2]))
			{
				foreach ($xml_company->$keys[0]->$keys[1]->$keys[2] as $data)
				{
					if ($data->location=="Work")
					{
						$existing_company_fields['email-work'] = $data->id;
					}
					if ($data->location=="Other")
					{
						$existing_company_fields['email-other'] = $data->id;
					}
				}
			}
			
			//phone(s)
			$keys = array('contact-data', 'phone-numbers', 'phone-number');
			if (isset($xml_company->$keys[0]->$keys[1]->$keys[2]))
			{
				foreach ($xml_company->$keys[0]->$keys[1]->$keys[2] as $data)
				{
					if ($data->location=="Work")
					{
						$existing_company_fields['phone-work'] = $data->id;
					}
					if ($data->location=="Other")
					{
						$existing_company_fields['phone-other'] = $data->id;
					}
				}
			}
			
			//address
			$keys = array('contact-data', 'addresses', 'address');
			if (isset($xml_company->$keys[0]->$keys[1]->$keys[2]))
			{
				foreach ($xml_company->$keys[0]->$keys[1]->$keys[2] as $data)
				{
					if ($data->location=="Work")
					{
						$existing_company_fields['address'] = $data->id;
					}
				}
			}
			
			//URL
			$keys = array('contact-data', 'web-addresses', 'web-address');
			if (isset($xml_company->$keys[0]->$keys[1]->$keys[2]))
			{
				foreach ($xml_company->$keys[0]->$keys[1]->$keys[2] as $data)
				{
					if ($data->location=="Work")
					{
						$existing_company_fields['url'] = $data->id;
					}
				}
			}
			
			//custom fields
			$keys = array('subject_datas', 'subject_data');
			if (isset($xml_company->$keys[0]->$keys[1]))
			{
				foreach ($xml_company->$keys[0]->$keys[1] as $data)
				{
					$existing_company_fields[(int)$data->id] = $data->id;
				}
			}
			
		}

		//echo "4";
		
		
		$this->EE->xml_writer->addNode('name', $company_name);
		
		$this->EE->xml_writer->startBranch('contact-data');
		
		if ($settings['remote_field_email-work']!='' && $q->row($settings['remote_field_email-work'])!='')
		{
			$this->EE->xml_writer->startBranch('email-addresses');
			$this->EE->xml_writer->startBranch('email-address');
			if (isset($existing_company_fields['email-work']))
			{
				$this->EE->xml_writer->addNode('id', $existing_company_fields['email-work'], array('type'=>'integer'));
			}
			$this->EE->xml_writer->addNode('address', $q->row($settings['remote_field_email-work']));
			$this->EE->xml_writer->addNode('location', "Work");
			$this->EE->xml_writer->endBranch();
			$this->EE->xml_writer->endBranch();
		}
		//echo "5";
		if (($settings['remote_field_phone-work']!='' && $q->row($settings['remote_field_phone-work'])!='') || ($settings['remote_field_phone-other']!='' && $q->row($settings['remote_field_phone-other'])!=''))
		{
			$this->EE->xml_writer->startBranch('phone-numbers');
			if ($settings['remote_field_phone-work']!='' && $q->row($settings['remote_field_phone-work'])!='')
			{
				$this->EE->xml_writer->startBranch('phone-number');
				if (isset($existing_company_fields['phone-work']))
				{
					$this->EE->xml_writer->addNode('id', $existing_company_fields['phone-work'], array('type'=>'integer'));
				}
				$this->EE->xml_writer->addNode('number', $q->row($settings['remote_field_phone-work']));
				$this->EE->xml_writer->addNode('location', "Work");
				$this->EE->xml_writer->endBranch();
			}
			if ($settings['remote_field_phone-other']!='' && $q->row($settings['remote_field_phone-other'])!='')
			{
				$this->EE->xml_writer->startBranch('phone-number');
				if (isset($existing_company_fields['phone-other']))
				{
					$this->EE->xml_writer->addNode('id', $existing_company_fields['phone-other'], array('type'=>'integer'));
				}
				$this->EE->xml_writer->addNode('number', $q->row($settings['remote_field_phone-other']));
				$this->EE->xml_writer->addNode('location', "Other");
				$this->EE->xml_writer->endBranch();
			}
			$this->EE->xml_writer->endBranch();
		}
		//echo "6";
		if (($settings['remote_field_city-work']!='' || $settings['remote_field_country-work']!='' || $settings['remote_field_state-work']!='' || $settings['remote_field_street-work']!='' || $settings['remote_field_zip-work']!='') && ($q->row($settings['remote_field_city-work'])!='' || $q->row($settings['remote_field_country-work'])!='' || $q->row($settings['remote_field_state-work'])!='' || $q->row($settings['remote_field_street-work'])!='' || $q->row($settings['remote_field_zip-work'])!=''))
		{
			$this->EE->xml_writer->startBranch('addresses');
			$this->EE->xml_writer->startBranch('address');
			if (isset($existing_company_fields['address']))
			{
				$this->EE->xml_writer->addNode('id', $existing_company_fields['address'], array('type'=>'integer'));
			}

			if ($settings['remote_field_city-work']!='' && $q->row($settings['remote_field_city-work'])!='')
			{
				$this->EE->xml_writer->addNode('city', $q->row($settings['remote_field_city-work']));
			}
			if ($settings['remote_field_country-work']!='' && $q->row($settings['remote_field_country-work'])!='')
			{
				$this->EE->xml_writer->addNode('country', $q->row($settings['remote_field_country-work']));
			}
			if ($settings['remote_field_state-work']!='' && $q->row($settings['remote_field_state-work'])!='')
			{
				$this->EE->xml_writer->addNode('state', $q->row($settings['remote_field_state-work']));
			}
			if ($settings['remote_field_street-work']!='' && $q->row($settings['remote_field_street-work'])!='')
			{
				$this->EE->xml_writer->addNode('street', $q->row($settings['remote_field_street-work']));
			}
			if ($settings['remote_field_zip-work']!='' && $q->row($settings['remote_field_zip-work'])!='')
			{
				$this->EE->xml_writer->addNode('zip', $q->row($settings['remote_field_zip-work']));
			}
			$this->EE->xml_writer->addNode('location', "Work");
		
			$this->EE->xml_writer->endBranch();
			$this->EE->xml_writer->endBranch();
		}
		
		//echo "7";
		if ($settings['remote_field_url']!='' && $q->row($settings['remote_field_url'])!='')
		{
			$this->EE->xml_writer->startBranch('web-addresses');
			$this->EE->xml_writer->startBranch('web-address');
			if (isset($existing_company_fields['url']))
			{
				$this->EE->xml_writer->addNode('id', $existing_company_fields['url'], array('type'=>'integer'));
			}
			//$this->EE->xml_writer->addNode('url', $this->EE->functions->create_url($settings['permalink_prefix_'.$group_id].'/'.$q->row($settings['remote_field_url'])));
			$this->EE->xml_writer->addNode('url', $this->EE->config->item('site_url').'/'.$settings['permalink_prefix_'.$group_id].'/'.$q->row($settings['remote_field_url']));
			$this->EE->xml_writer->addNode('location', "Work");
			$this->EE->xml_writer->endBranch();
			$this->EE->xml_writer->endBranch();
		}
		//var_dump($settings['remote_field_url']);
		//echo $this->EE->functions->create_url($settings['permalink_prefix_'.$group_id].'/'.$q->row($settings['remote_field_url']));
		
		
		$this->EE->xml_writer->endBranch();
		
		$this->EE->xml_writer->startBranch('subject_datas', array('type'=>'array'));
		//loop through the list of custom fields
		foreach ($highrise_fields as $field_id=>$field_label)
		{
			if (isset($settings['remote_field_'.$field_id]) && $settings['remote_field_'.$field_id]!='')
			{
				$this->EE->xml_writer->startBranch('subject_data');
				if (isset($existing_company_fields[$field_id]))
				{
					$this->EE->xml_writer->addNode('id', $existing_company_fields[$field_id], array('type'=>'integer'));
				}
				//$custom_field_id = str_replace('remote_field_', '', array_search('remote_field_'.$field_id, $settings));
				
				$this->EE->xml_writer->addNode('value', $q->row($settings['remote_field_'.$field_id]));
				$this->EE->xml_writer->addNode('subject_field_id', $field_id, array('type'=>'integer'));
				$this->EE->xml_writer->addNode('subject_field_label', $field_label, array('type'=>'integer'));
				$this->EE->xml_writer->endBranch();
			}
		}
		$this->EE->xml_writer->endBranch();

		//echo "6";
		$post_xml = $this->EE->xml_writer->getXml();
		////echo $post_xml;
		
		//xml created, let's post company info
		$this->_curl_init($settings['highrise_api_key']);
		if ($highrise_company_id==false)
		{
			$result = $this->EE->curl->simple_post("https://" . $settings['highrise_account'] . ".highrisehq.com/companies.xml", $post_xml);
		}
		else
		{
			$this->EE->curl->create("https://" . $settings['highrise_account'] . ".highrisehq.com/companies/".$highrise_company_id.".xml");
	    	$this->EE->curl->put($post_xml);
			$this->EE->curl->execute();
		}
				
		//$this->EE->curl->debug();
		
		//if company has just beed created, get its ID
		if ($highrise_company_id==false)
		{
			if ($result!=false) 
	 		{
	 			$xml = new SimpleXMLElement($result);
				$highrise_company_id = 	$xml->id;
			}
		}
		
		//parse the person's data into array
		if (isset($highrise_member_data))
		{
			//email(s)
			$keys = array('contact-data', 'email-addresses', 'email-address');
			if (isset($highrise_member_data->$keys[0]->$keys[1]->$keys[2]))
			{
				foreach ($highrise_member_data->$keys[0]->$keys[1]->$keys[2] as $data)
				{
					if ($data->location=="Home")
					{
						$existing_person_fields['email-home'] = $data->id;
					}
					if ($data->location=="Work")
					{
						$existing_person_fields['email-work'] = $data->id;
					}
				}
			}
			//custom fields
			$keys = array('subject_datas', 'subject_data');
			if (isset($highrise_member_data->$keys[0]->$keys[1]))
			{
				foreach ($highrise_member_data->$keys[0]->$keys[1] as $data)
				{
					$existing_person_fields[(int)$data->id] = (int)$data->id;
				}
			}
		}
    	
    	//now, build XML for PERSON data
    	$this->EE->xml_writer->setRootName('person');
		$this->EE->xml_writer->initiate();
    	if ($highrise_person_id !=false)
  		{
  			$this->EE->xml_writer->addNode('id', $highrise_person_id, array('type'=>'integer'));
		}
		$this->EE->xml_writer->addNode('first-name', $q->row($settings['remote_field_first-name']));
		$this->EE->xml_writer->addNode('last-name', $q->row($settings['remote_field_last-name']));
		$this->EE->xml_writer->addNode('company-id', $highrise_company_id);
		
		$this->EE->xml_writer->startBranch('contact-data');
		
		if (($settings['remote_field_email-home']!='' && $q->row($settings['remote_field_email-home'])!='')||($settings['remote_field_email-work']!='' && $q->row($settings['remote_field_email-work'])!=''))
		{
			$this->EE->xml_writer->startBranch('email-addresses');
			if ($settings['remote_field_email-home']!='' && $q->row($settings['remote_field_email-home'])!='')
			{
				$this->EE->xml_writer->startBranch('email-address');
				if (isset($existing_person_fields['email-home']))
				{
					$this->EE->xml_writer->addNode('id', $existing_person_fields['email-home'], array('type'=>'integer'));
				}
				$this->EE->xml_writer->addNode('address', $q->row($settings['remote_field_email-home']));
				$this->EE->xml_writer->addNode('location', "Home");
				$this->EE->xml_writer->endBranch();
			}
			if ($settings['remote_field_email-work']!='' && $q->row($settings['remote_field_email-work'])!='')
			{
				$this->EE->xml_writer->startBranch('email-address');
				if (isset($existing_person_fields['email-work']))
				{
					$this->EE->xml_writer->addNode('id', $existing_person_fields['email-work'], array('type'=>'integer'));
				}
				$this->EE->xml_writer->addNode('address', $q->row($settings['remote_field_email-work']));
				$this->EE->xml_writer->addNode('location', "Work");
				$this->EE->xml_writer->endBranch();
			}
			$this->EE->xml_writer->endBranch();
		}
		
		
		
		$this->EE->xml_writer->endBranch();
		
    	
    	$this->EE->xml_writer->startBranch('subject_datas', array('type'=>'array'));
		//loop through the list of custom fields
		foreach ($highrise_fields as $field_id=>$field_label)
		{
			if (isset($settings['remote_field_'.$field_id]) && $settings['remote_field_'.$field_id]!='')
			{
				$this->EE->xml_writer->startBranch('subject_data');
				if (isset($existing_person_fields[$field_id]))
				{
					$this->EE->xml_writer->addNode('id', $existing_person_fields[$field_id], array('type'=>'integer'));
				}
				//$custom_field_id = str_replace('remote_field_', '', array_search('remote_field_'.$field_id, $settings));
				
				$this->EE->xml_writer->addNode('value', $q->row($settings['remote_field_'.$field_id]));
				$this->EE->xml_writer->addNode('subject_field_id', $field_id, array('type'=>'integer'));
				$this->EE->xml_writer->addNode('subject_field_label', $field_label, array('type'=>'integer'));
				$this->EE->xml_writer->endBranch();
			}
		}
		$this->EE->xml_writer->endBranch();

		
		$post_xml = $this->EE->xml_writer->getXml();
    	
    	//echo $post_xml;
    	//xml created, let's post personal info
		$this->_curl_init($settings['highrise_api_key']);
		if ($highrise_person_id==false)
		{
			$result = $this->EE->curl->simple_post("https://" . $settings['highrise_account'] . ".highrisehq.com/people.xml", $post_xml);
			//echo $result;
		}
		else
		{
			$this->EE->curl->create("https://" . $settings['highrise_account'] . ".highrisehq.com/people/".$highrise_person_id.".xml");
	    	$this->EE->curl->put($post_xml);
			$result = $this->EE->curl->execute();
			//echo $result;
		}
		
				
		//$this->EE->curl->debug();
		
		//if person has just beed created, get their ID
		if ($highrise_person_id==false)
		{
			if ($result!=false) 
	 		{
	 			$xml = new SimpleXMLElement($result);
	 			$highrise_person_id = 	$xml->id;
 				$this->_curl_init($settings['highrise_api_key']);
		
		    	$result = $this->EE->curl->simple_get("https://" . $settings['highrise_account'] . ".highrisehq.com/people/$highrise_person_id.xml");
		 		if ($result!=false) 
		 		{
		 			$xml = new SimpleXMLElement($result);
		 			$highrise_member_data = $xml;
		 		}
			}
		}
		
		
		
		//set crm_id custom field in EE
		$field = array_search($settings['highrise_person_id_field'], $member_fields);
		if ($field!==false)
		{
			$data = array($field=>(int)$highrise_person_id);
			$this->EE->db->where('member_id', $member_id);
			$this->EE->db->update('member_data', $data);
		}

		//update tags
		//for the person
		
		if (!isset($highrise_member_data))
		{
			return false;
		}
		
		if (isset($highrise_member_data->tags->tag))
		{
			$remove_tags = array();
			foreach ($settings as $name=>$val)
			{
				if(strpos($name, 'remote_group_')!==false)
				{
					$remove_tags[] = $val;
				}
			}	
			if (is_array($highrise_member_data->tags->tag))
			{
				$existing_tags = $highrise_member_data->tags->tag;
			}
			else
			{
				$existing_tags = array($highrise_member_data->tags->tag);
			}
			foreach ($existing_tags as $existing_tag)
			{
				if (in_array($existing_tag->name, $remove_tags))
				{
					$this->_curl_init($settings['highrise_api_key']);
			    	$this->EE->curl->simple_delete("https://" . $settings['highrise_account'] . ".highrisehq.com/people/".$highrise_person_id."/tags/".$existing_tag->id.".xml");
				}
			}
		}
//echo "tags:";
//echo $settings['remote_group_'.$group_id];
		if ($settings['remote_group_'.$group_id]!='')
		{
		
			$this->_curl_init($settings['highrise_api_key']);
			$r = $this->EE->curl->simple_post("https://" . $settings['highrise_account'] . ".highrisehq.com/people/".$highrise_person_id."/tags.xml", "<name>".$settings['remote_group_'.$group_id]."</name>");
			//var_dump($r);
		}
		
		
		
		//for the company
		//we'll set same values as for person, if there are multiple people - mass sync will take care if it
		
		$this->_curl_init($settings['highrise_api_key']);
			
    	$result = $this->EE->curl->simple_get("https://" . $settings['highrise_account'] . ".highrisehq.com/companies/$highrise_company_id.xml");
 		if ($result!=false) 
 		{
 			$xml = new SimpleXMLElement($result);
 			$highrise_company_data = $xml;
 		}
		
		if (isset($highrise_company_data->tags->tag))
		{
			$remove_tags = array();
			foreach ($settings as $name=>$val)
			{
				if(strpos($name, 'remote_group_')!==false)
				{
					$remove_tags[] = $val;
				}
			}	
			if (is_array($highrise_company_data->tags->tag))
			{
				$existing_tags = $highrise_company_data->tags->tag;
			}
			else
			{
				$existing_tags = array($highrise_company_data->tags->tag);
			}
			foreach ($existing_tags as $existing_tag)
			{
				if (in_array($existing_tag->name, $remove_tags))
				{
					$this->_curl_init($settings['highrise_api_key']);
			    	$this->EE->curl->simple_delete("https://" . $settings['highrise_account'] . ".highrisehq.com/companies/".$highrise_company_id."/tags/".$existing_tag->id.".xml");
				}
			}
		}
		
		if ($settings['remote_group_'.$group_id]!='')
		{
		
			$this->_curl_init($settings['highrise_api_key']);
			$this->EE->curl->simple_post("https://" . $settings['highrise_account'] . ".highrisehq.com/companies/".$highrise_company_id."/tags.xml", "<name>".$settings['remote_group_'.$group_id]."</name>");
			
		}
		
		
		//aaaand success!!!!
		return true;
    	
	}
	
	

}
/* END */
?>