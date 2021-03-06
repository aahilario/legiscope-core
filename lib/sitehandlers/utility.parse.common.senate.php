<?php

/*
 * Class SenateCommonParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateCommonParseUtility extends LegislationCommonParseUtility {
  
  function __construct() {
    parent::__construct();
  }

  function ru_head_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_head_close(& $parser, $tag) {/*{{{*/
    array_pop($this->container_stack);
    return FALSE;
  }/*}}}*/

  function ru_div_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, $this->get_stacktags() . "--- {$this->current_tag['tag']}" );
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ($this->debug_tags) $this->recursive_dump($attrs,__LINE__);
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_div_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, $this->get_stacktags() . "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_div_close(& $parser, $tag) {/*{{{*/
    $skip = FALSE;
    $this->pop_tagstack();
    if ( is_array($this->current_tag) && array_key_exists('attrs', $this->current_tag) ) {
      if ( array_key_exists('CLASS',$this->current_tag['attrs']) && array_key_exists($this->current_tag['attrs']['CLASS'],array_flip(array(
        'nav_dropdown',
        'div_hidden',
        'more',
        'sidepane_nav',
        'header_pane',
      )))) $skip = TRUE;
      if ( array_key_exists('ID',$this->current_tag['attrs']) && array_key_exists($this->current_tag['attrs']['ID'],array_flip(array(
        'nav_top',
        'nav_bottom',
        'lis_changecongress',
        'div_Help',
      )))) $skip = TRUE;
      if ( $skip && $this->debug_tags ) {
        usleep(20000);
        $tag_cdata = array_key_exists('cdata', $this->current_tag) ? join('', $this->current_tag['cdata']) : '--empty--';
        $this->syslog(__FUNCTION__,__LINE__,"(marker) Warning - Rejecting tag with CDATA [{$tag_cdata}]");
        $this->recursive_dump($this->current_tag,"(warning) {$tag}" );
      }
    }
    $this->push_tagstack();
    if (is_array($this->current_tag) && !$skip ) $this->stack_to_containers();
    
    return !$skip;
  }/*}}}*/

  function ru_br_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_br_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_br_close(& $parser, $tag) {/*{{{*/
    $me     = $this->pop_tagstack();
    $parent = $this->pop_tagstack();
    if ( array_key_exists('cdata', $parent) ) {
      // $this->syslog(__FUNCTION__,__LINE__,"Adding line break to {$parent['tag']} (" . join(' ', $parent['cdata']) . ")" );
      $parent['cdata'][] = "\n[BR]";
    }
    $this->push_tagstack($parent);
    $this->push_tagstack($me);
    return FALSE;
  }/*}}}*/

  function ru_a_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->update_current_tag_url('HREF');
    if ( !array_key_exists('CLASS', $this->current_tag['attrs']) ) {
      // $this->syslog(__FUNCTION__,__LINE__,"Setting CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] = 'legiscope-remote';
      $this->current_tag['attrs']['ID'] = array_key_exists('HREF',$this->current_tag['attrs']) ? UrlModel::get_url_hash($this->current_tag['attrs']['HREF']) : NULL;
    } else {
      // $this->syslog(__FUNCTION__,__LINE__,"Adding CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] .= ' legiscope-remote';
    }
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, $this->get_stacktags() . " --- {$this->current_tag['tag']} {$this->current_tag['attrs']['HREF']}" );
    return TRUE;
  }/*}}}*/
  function ru_a_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_a_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $link_data = $this->collapse_current_tag_link_data();
    $this->add_to_container_stack($link_data);
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function ru_link_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }  /*}}}*/
  function ru_link_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_link_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_style_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }  /*}}}*/
  function ru_style_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_style_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_img_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->update_current_tag_url('SRC');
    $this->push_tagstack();
    return TRUE;
  }  /*}}}*/
  function ru_img_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_img_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    return !(is_array($this->current_tag) && array_key_exists('attrs',$this->current_tag) && array_key_exists('CLASS',$this->current_tag['attrs']) && (1 == preg_match('@(nav_logo)@i',$this->current_tag['attrs']['CLASS'])));
  }/*}}}*/

  function session_select_option_assignments(& $control_set, $select_option, $select_name) {/*{{{*/
    // This method must be defined for Congress pagers as well
    $control_set['__EVENTTARGET']   = $select_name;
    $control_set['__EVENTARGUMENT'] = NULL;
  }/*}}}*/

	// POST wall traversal (converting POST actions to proxied GET)
	function site_form_traversal_controls(UrlModel & $action_url, $form_controls ) {
		$form_controls['__EVENTTARGET'] = 'lbAll';
		$form_controls['__EVENTARGUMENT'] = NULL;
		return $form_controls;
	}/*}}}*/

  function jquery_seek_missing_journal_pdf() {/*{{{*/
    return <<<EOH

<script type="text/javascript">

jQuery(document).ready(function(){
  jQuery('a[class*=journal-pdf]').each(function() {
    if ( jQuery(this).hasClass('uncached') ) {
      var self = jQuery(this);
      var linkurl = jQuery(this).attr('href');
      jQuery.ajax({
        type     : 'POST',
        url      : '/seek/',
        data     : { url : linkurl, update : jQuery('#update').prop('checked'), proxy : jQuery('#proxy').prop('checked'), modifier : jQuery('#seek').prop('checked'), fr: true },
        cache    : false,
        dataType : 'json',
        async    : false,
        beforeSend : (function() {
          display_wait_notification();
        }),
        complete : (function(jqueryXHR, textStatus) {
          remove_wait_notification();
        }),
        success  : (function(data, httpstatus, jqueryXHR) {
          jQuery(self).addClass('cached').removeClass('uncached');
          if ( data && data.original ) replace_contentof('original',data.original);
          if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
        })
      });
    }
  });
});

</script>

EOH;
  }/*}}}*/

}

