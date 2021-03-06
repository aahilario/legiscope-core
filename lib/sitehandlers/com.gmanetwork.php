<?php

/*
 * Class GmanetworkCom
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class GmanetworkCom extends SeekAction {
  
  function __construct() {
    $this->syslog( __FUNCTION__, 'FORCE', 'Using site-specific container class' );
    parent::__construct();
  }

  function common_unhandled_page_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $common = new GmanetworkCommonParseUtility();
    $urlmodel->ensure_custom_parse();
    $common->set_parent_url($urlmodel->get_url())->parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());
    $this->recursive_dump(($containers = $common->get_containers(
    )),'(marker) A');


    $linkset = $common->generate_linkset($urlmodel->get_url());
    
    extract($linkset);
    $parser->linkset = preg_replace('@cached@i','uncached',$linkset);
    $pagecontent = preg_replace(
      array(
        "@\&\#10;@",
        "@\n@",
      ),
      array(
        "",
        "",
      ),
      join('',$common->get_filtered_doc())
    );
  }/*}}}*/

  function must_custom_parse(UrlModel & $url) {/*{{{*/
    return TRUE; 
  }/*}}}*/

}

