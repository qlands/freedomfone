<?php
/****************************************************************************
 * callback_service.php  - Model for SMS based Callback Services. Mapping SMS code with extension.
 * version 		 - 2.5.1300
 * 
 * Version: MPL 1.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 *
 * The Initial Developer of the Original Code is
 *   Louise Berthilson <louise@it46.se>
 *
 *
 ***************************************************************************/


class CallbackService extends AppModel{

      var $name = 'CallbackService';

      var $hasMany = array('Callback' => array(
                        	       'order' => 'Callback.id ASC',
                        	       'dependent' => true)
				       ); 




      function __construct($id = false, $table = null, $ds = null) {
         parent::__construct($id, $table, $ds);

         $this->validate = array(
                'extension' => array(
                      'notEmpty' =>  array(
                                     'rule' => 'notEmpty',
                                     'message'  => __('You must select a service.',true),
                                     'required' => true,
                                     ),
                      'range' =>  array(
                                     'rule' => array('range', 2100, 4120),
                                     'message'  => __('You must select a service.',true),
                                     'required' => true,
                                     )),
                'code' => array(
                    'notEmpty' => array(
                            'rule' => 'notEmpty',
                            'message'  => __('Please state an SMS code for the callback service.',true),
                            'required' => true,
                            ),
                    'IsUnique' => array(
                            'rule' => 'isUnique',
                            'message'  => __('The SMS code provided is alrady in use.',true),
                            'required' => true,
                            )));

      }



/**
 * Check if the number of callbacks allowed (within a certain time limit) for a user has exceeded its maximum value or not
 * return true| false
 *
 */
	function withinLimit($sender){

		 $data = $this->query("select * from callback_settings limit 0,1");

		       if ($data[0]['callback_settings']['limit_time']==0 | $data[0]['callback_settings']['limit_user'] ==0 ){
	    	       	  return true;
			  }
		       else {

		       	$epoch_limit = time()-$data[0]['callback_settings']['limit_time']*3600;
			$user_limit  = $data[0]['callback_settings']['limit_user'];
			$response = $this->find('count', array('conditions' => array('Callback.sender' => $sender,'Callback.created >' => $epoch_limit,'Callback.status' =>'1')));

		    		  if($response >= $user_limit){
		        	  	       return false;
		     			       }
		     			       else {
		     			       return true;
		     	             }
		   }

	}



}

?>
