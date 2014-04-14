<?php

	#################################################################

	loadlib("dots");
	loadlib("users");
	
	#################################################################

	function api_dots_dotsForUser(){
	    
	    // these keys not important
	    $skipKeys = array("details","details_json","index_on","details_listview","type_of_co");
	    
	    $u = request_str('user');
	    $owner = users_get_by_id($u);
	    $output = array();
	    if($owner){
	        $dots = dots_get_dots_for_user($owner);
	        
	        // please say there is a better way 
	        if($dots){
    	        foreach ($dots as &$row){
    	            $a = array();

    	            foreach($row as $k=>$v){
    	                if(!in_array($k,$skipKeys)){
                            $a[$k]=$v;
        	            }
    	            }

    	           $output[]=$a;
    	        }
            }
            
	    }
	    
	    if(count($output)){
	        api_output_ok($output);
	    }else{
	        api_output_error();
	    }
	    
	}

	#################################################################

	# the end 
