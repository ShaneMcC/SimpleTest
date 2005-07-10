<?php
    /**
     *	Base include file for SimpleTest.
     *	@package	SimpleTest
     *	@subpackage	WebTester
     *	@version	$Id$
     */
     
    /**#@+
     * include SimpleTest files
     */
    require_once(dirname(__FILE__) . '/tag.php');
    require_once(dirname(__FILE__) . '/encoding.php');
    require_once(dirname(__FILE__) . '/selector.php');
    /**#@-*/
    
    /**
     *    Form tag class to hold widget values.
	 *    @package SimpleTest
	 *    @subpackage WebTester
     */
    class SimpleForm {
        var $_method;
        var $_action;
        var $_encoding;
        var $_default_target;
        var $_id;
        var $_buttons;
        var $_images;
        var $_widgets;
        var $_radios;
        var $_checkboxes;
        
        /**
         *    Starts with no held controls/widgets.
         *    @param SimpleTag $tag        Form tag to read.
         *    @param SimpleUrl $url        Location of holding page.
         */
        function SimpleForm($tag, $url) {
            $this->_method = $tag->getAttribute('method');
            $this->_action = $this->_createAction($tag->getAttribute('action'), $url);
            $this->_encoding = $this->_createEmptyEncoding($tag);
            $this->_default_target = false;
            $this->_id = $tag->getAttribute('id');
            $this->_buttons = array();
            $this->_images = array();
            $this->_widgets = array();
            $this->_radios = array();
            $this->_checkboxes = array();
        }
        
        /**
         *    Creates the request packet to be sent by the form.
         *    @param SimpleTag $tag        Form tag to read.
         *    @return SimpleEncoding       Packet.
         *    @access private
         */
        function _createEmptyEncoding($tag) {
            if (strtolower($tag->getAttribute('method')) == 'post') {
                if (strtolower($tag->getAttribute('enctype')) == 'multipart/form-data') {
                    return new SimpleMultipartEncoding();
                }
                return new SimplePostEncoding();
            }
            return new SimpleGetEncoding();
        }
        
        /**
         *    Sets the frame target within a frameset.
         *    @param string $frame        Name of frame.
         *    @access public
         */
        function setDefaultTarget($frame) {
            $this->_default_target = $frame;
        }
        
        /**
         *    Accessor for form action.
         *    @return string           Either get or post.
         *    @access public
         */
        function getMethod() {
            return ($this->_method ? strtolower($this->_method) : 'get');
        }
        
        /**
         *    Combined action attribute with current location
         *    to get an absolute form target.
         *    @param string $action    Action attribute from form tag.
         *    @param SimpleUrl $base   Page location.
         *    @return SimpleUrl        Absolute form target.
         */
        function _createAction($action, $base) {
            if (is_bool($action)) {
                return $base;
            } else {
                $url = new SimpleUrl($action);
            }
            return $url->makeAbsolute($base);
        }
        
        /**
         *    Absolute URL of the target.
         *    @return SimpleUrl           URL target.
         *    @access public
         */
        function getAction() {
            $url = $this->_action;
            if ($this->_default_target && ! $url->getTarget()) {
                $url->setTarget($this->_default_target);
            }
            return $url;
        }
       
        /**
         *    Creates the encoding for the current values in the
         *    form.
         *    @return SimpleFormEncoding    Request to submit.
         *    @access private
         */
        function _encode() {
            $encoding = $this->_encoding;
            for ($i = 0, $count = count($this->_widgets); $i < $count; $i++) {
                $encoding->add(
                        $this->_widgets[$i]->getName(),
                        $this->_widgets[$i]->getValue());
            }
            return $encoding;
        }
                
        /**
         *    ID field of form for unique identification.
         *    @return string           Unique tag ID.
         *    @access public
         */
        function getId() {
            return $this->_id;
        }
        
        /**
         *    Adds a tag contents to the form.
         *    @param SimpleWidget $tag        Input tag to add.
         *    @access public
         */
        function addWidget(&$tag) {
            if (strtolower($tag->getAttribute('type')) == 'submit') {
                $this->_buttons[] = &$tag;
            } elseif (strtolower($tag->getAttribute('type')) == 'image') {
                $this->_images[] = &$tag;
            } elseif ($tag->getName()) {
                $this->_setWidget($tag);
            }
        }
        
        /**
         *    Sets the widget into the form, grouping radio
         *    buttons if any.
         *    @param SimpleWidget $tag   Incoming form control.
         *    @access private
         */
        function _setWidget(&$tag) {
            if (strtolower($tag->getAttribute('type')) == 'radio') {
                $this->_addRadioButton($tag);
            } elseif (strtolower($tag->getAttribute('type')) == 'checkbox') {
                $this->_addCheckbox($tag);
            } else {
                $this->_widgets[] = &$tag;
            }
        }
        
        /**
         *    Adds a radio button, building a group if necessary.
         *    @param SimpleRadioButtonTag $tag   Incoming form control.
         *    @access private
         */
        function _addRadioButton(&$tag) {
            if (! isset($this->_radios[$tag->getName()])) {
                $this->_widgets[] = &new SimpleRadioGroup();
                $this->_radios[$tag->getName()] = count($this->_widgets) - 1;
            }
            $this->_widgets[$this->_radios[$tag->getName()]]->addWidget($tag);
        }
        
        /**
         *    Adds a checkbox, making it a group on a repeated name.
         *    @param SimpleCheckboxTag $tag   Incoming form control.
         *    @access private
         */
        function _addCheckbox(&$tag) {
            if (! isset($this->_checkboxes[$tag->getName()])) {
                $this->_widgets[] = &$tag;
                $this->_checkboxes[$tag->getName()] = count($this->_widgets) - 1;
            } else {
                $index = $this->_checkboxes[$tag->getName()];
                if (! SimpleTestCompatibility::isA($this->_widgets[$index], 'SimpleCheckboxGroup')) {
                    $previous = &$this->_widgets[$index];
                    $this->_widgets[$index] = &new SimpleCheckboxGroup();
                    $this->_widgets[$index]->addWidget($previous);
                }
                $this->_widgets[$index]->addWidget($tag);
            }
        }
        
        /**
         *    Extracts current value from form.
         *    @param SimpleSelector $selector   Criteria to apply.
         *    @return string/array              Value(s) as string or null
         *                                      if not set.
         *    @access public
         */
        function getValueBySelector($selector) {
            for ($i = 0, $count = count($this->_widgets); $i < $count; $i++) {
                if ($selector->isMatch($this->_widgets[$i])) {
                    return $this->_widgets[$i]->getValue();
                }
            }
            foreach ($this->_buttons as $button) {
                if ($selector->isMatch($button)) {
                    return $button->getValue();
                }
            }
            return null;
        }
        
        /**
         *    Extracts current value from form.
         *    @param string $name        Keyed by widget name.
         *    @return string/array       Value(s) or null
         *                               if not set.
         *    @access public
         */
        function getValue($name) {
            return $this->getValueBySelector(new SimpleSelectByName($name));
        }
        
        /**
         *    Extracts current value from form by the label.
         *    @param string $label       External label content.
         *    @return string/array       Value(s) or null
         *                               if not set.
         *    @access public
         */
        function getValueByLabel($name) {
            return $this->getValueBySelector(new SimpleSelectByLabel($name));
        }
        
        /**
         *    Extracts current value from form by the ID.
         *    @param string/integer $id  Keyed by widget ID attribute.
         *    @return string/array       Value(s) or null
         *                               if not set.
         *    @access public
         */
        function getValueById($id) {
            return $this->getValueBySelector(new SimpleSelectById($id));
        }
        
        /**
         *    Sets a widget value within the form.
         *    @param SimpleSelector $selector   Criteria to apply.
         *    @param string $value              Value to input into the widget.
         *    @return boolean                   True if value is legal, false
         *                                      otherwise. If the field is not
         *                                      present, nothing will be set.
         *    @access public
         */
        function setFieldBySelector($selector, $value) {
            $success = false;
            for ($i = 0, $count = count($this->_widgets); $i < $count; $i++) {
                if ($selector->isMatch($this->_widgets[$i])) {
                    if ($this->_widgets[$i]->setValue($value)) {
                        $success = true;
                    }
                }
            }
            return $success;
        }
        
        /**
         *    Sets a widget value within the form by name only.
         *    @param string $name     Name of widget tag.
         *    @param string $value    Value to input into the widget.
         *    @return boolean         True if value is legal, false
         *                            otherwise. If the field is not
         *                            present, nothing will be set.
         *    @access public
         */
        function setFieldByName($name, $value) {
            return $this->setFieldBySelector(new SimpleSelectByName($name), $value);
        }
        
        /**
         *    Sets a widget value within the form by label tag.
         *    @param string $label    label associated with widget tag.
         *    @param string $value    Value to input into the widget.
         *    @return boolean         True if value is legal, false
         *                            otherwise. If the field is not
         *                            present, nothing will be set.
         *    @access public
         */
        function setFieldByLabel($label, $value) {
            return $this->setFieldBySelector(new SimpleSelectByLabel($label), $value);
        }
         
        /**
         *    Sets a widget value within the form by using the ID.
         *    @param string/integer $id   Name of widget tag.
         *    @param string $value        Value to input into the widget.
         *    @return boolean             True if value is legal, false
         *                                otherwise. If the field is not
         *                                present, nothing will be set.
         *    @access public
         */
        function setFieldById($id, $value) {
            return $this->setFieldBySelector(new SimpleSelectById($id), $value);
        }
        
        /**
         *    Used by the page object to set widgets labels to
         *    external label tags.
         *    @param string $label        Label to attach.
         *    @access public
         */
        function setLabelById($id, $label) {
            $selector = new SimpleSelectById($id);
            for ($i = 0, $count = count($this->_widgets); $i < $count; $i++) {
                if ($selector->isMatch($this->_widgets[$i])) {
                    $this->_widgets[$i]->setLabel($label);
                    return;
                }
            }
        }
        
        /**
         *    Test to see if a form has a submit button.
         *    @param SimpleSelector $selector   Criteria to apply.
         *    @return boolean                   True if present.
         *    @access private
         *    @access public
         */
        function hasSubmitBySelector($selector) {
            foreach ($this->_buttons as $button) {
                if ($selector->isMatch($button)) {
                    return true;
                }
            }
            return false;
        }
        
        /**
         *    Test to see if a form has an image control.
         *    @param SimpleSelector $selector   Criteria to apply.
         *    @return boolean                   True if present.
         *    @access public
         */
        function hasImageBySelector($selector) {
            foreach ($this->_images as $image) {
                if ($selector->isMatch($image)) {
                    return true;
                }
            }
            return false;
        }
       
        /**
         *    Gets the submit values for a selected button.
         *    @param SimpleSelector $selector   Criteria to apply.
         *    @param hash $additional           Additional data for the form.
         *    @return SimpleEncoding            Submitted values or false
         *                                      if there is no such button
         *                                      in the form.
         *    @access public
         */
        function submitButtonBySelector($selector, $additional = false) {
            $additional = $additional ? $additional : array();
            foreach ($this->_buttons as $button) {
                if ($selector->isMatch($button)) {
                    $encoding = $this->_encode();
                    $encoding->merge($button->getSubmitValues());
                    if ($additional) {
                        $encoding->merge($additional);
                    }
                    return $encoding;           
                }
            }
            return false;
        }
         
        /**
         *    Gets the submit values for an image.
         *    @param SimpleSelector $selector   Criteria to apply.
         *    @param integer $x                 X-coordinate of click.
         *    @param integer $y                 Y-coordinate of click.
         *    @param hash $additional           Additional data for the form.
         *    @return SimpleEncoding            Submitted values or false
         *                                      if there is no such button in the
         *                                      form.
         *    @access public
         */
        function submitImageBySelector($selector, $x, $y, $additional = false) {
            $additional = $additional ? $additional : array();
            foreach ($this->_images as $image) {
                if ($selector->isMatch($image)) {
                    $encoding = $this->_encode();
                    $encoding->merge($image->getSubmitValues($x, $y));
                    if ($additional) {
                        $encoding->merge($additional);
                    }
                    return $encoding;           
                }
            }
            return false;
        }
      
        /**
         *    Simply submits the form without the submit button
         *    value. Used when there is only one button or it
         *    is unimportant.
         *    @return hash           Submitted values.
         *    @access public
         */
        function submit() {
            return $this->_encode();
        }
    }
?>