/**
 * 
 */
Drupal.wysiwyg.plugins.os_link = {
  url: '',
  
  /**
   * Determines if element belongs to this plugin or not
   * Returning true will cause the button to be 'down' when an element is selected
   */
  isNode: function (node) {
    if (node == null || node == undefined) return false;
    while (node.nodeName != 'A' && node.nodeName != 'BODY') {
      node = node.parentNode;
    } 
    return node.nodeName == 'A';
  },
  
  invoke: function (selection, settings, editorId) {
    var self = this;
    if (this.isNode(selection.node)) {
      var link = jQuery(selection.node);
      if (link[0].nodeName != 'A') {
        link = link.find('a');
      }
      if (link.length == 0) {
        link = jQuery(selection.node).parents('a');
      }
      var info = this.parseAnchor(link[0]);
      settings['global'].active = info.type;
      settings['global'].url = info.url;
    }
    else {
      delete settings['global'].active;
      delete settings['global'].url;
    }
    Drupal.media.popups.mediaBrowser(function (insert) {
      self.insertLink();
    }, settings['global'], {}, {
      src: Drupal.settings.osWysiwygLink.browserUrl, // because media is dumb about its query args
      onLoad: function (e) { self.popupOnLoad(e, selection, editorId); }
    });
  },
  
  insertLink: function (editorId, body, target, attributes) {
    var html = '<a href="'+target+'">'+body+'</a>';
    
    if (attributes) {
      var $html = jQuery(html);
      $html.attr(attributes);
      html = typeof $html[0].outerHTML != 'undefined' 
              ? $html[0].outerHTML 
              : $html.wrap('<div>').parent().html();
    }
    
    Drupal.wysiwyg.instances[editorId].insert(html);
  },
  
  popupOnLoad: function (e, selection, editorId) {
    // bind handlers to the insert button
    // each 'form' should have a little script to generate an anchor tag or do something else with the data
    // this scripts should put the generated tag somewhere consistent
    // this function will bind a handler to take the tag and have it inserted into the wysiwyg
    var $ = jQuery, 
      self = this,
      iframe = e.currentTarget,
      doc = $(iframe.contentDocument),
      window = iframe.contentWindow,
      selected = '[Selected content. Click here to change it.]';
    
    if (selection.content != '') {
      $('.form-item-link-text input', doc).val(selected);
    }
    
    $('.insert-buttons input[value="Insert"]', doc).click(function (e) {
      $('.vertical-tabs form:visible .form-actions input[value="Insert"]', doc).click();
      
      var attrs = typeof window.Drupal.settings.osWysiwygLinkAttributes != 'undefined' 
            ? window.Drupal.settings.osWysiwygLinkAttributes 
            : false,
          text = $('.form-item-link-text input', doc).val() == '[Selected content. Click here to change it.]'
            ? selection.content
            : $('.form-item-link-text input', doc).val(); 
      self.insertLink(editorId, text, window.Drupal.settings.osWysiwygLinkResult, attrs);
      $(iframe).dialog('destroy');
      $(iframe).remove();
    });
    
    $('.insert-buttons input[value="Cancel"]', doc).click(function (e) {
      $(iframe).dialog('destroy');
      $(iframe).remove();
    });
  },
  
  /**
   * Reads an anchor tag to determine whether it's internal, external, an e-mail or a link to a file
   * @param a
   * @return {link text, link url, link type}
   */
  parseAnchor: function (a) {
    var ret = {
      text: a.innerHTML,
      url: '',
      type: ''
    };
    if (a.hasAttribute('data-fid')) {
      ret.url = a.getAttribute('data-fid');
      ret.type = 'file';
    }
    else if (a.origin == 'mailto://') {
      ret.url = a.pathname;
      ret.type = 'email';
    }
    else {
      var home = Drupal.settings.basePath + (typeof Drupal.settings.pathPrefix != 'undefined'?Drupal.settings.pathPrefix:''),
          dummy = document.createElement('a');
      dummy.href = home;
      if (dummy.hostname == a.hostname && a.pathname.indexOf(dummy.pathname) != -1) {
        // internal link
        ret.url = a.pathname.replace(home, '');
        ret.type = 'internal';
      }
      else {
        ret.url = a.href;
        ret.type = 'external';
      }
      
    }
    return ret;
  },
  
  /**
   * Converts link media tags into anchor tags
   */
  attach: function (content, settings, instanceId) {
    return content;
  },
  
  /**
   * Converts links to files into media tags
   */
  detach: function (content, settings, instanceId) {        
    return content;
  }
};