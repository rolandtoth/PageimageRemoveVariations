<?php

/**
 * ProcessWire module that removes images-cache sitewide
 *
 *
 * ProcessWire 2.x
 * Copyright (C) 2010 by Ryan Cramer
 * Licensed under GNU/GPL v2, see LICENSE.TXT
 *
 * http://www.processwire.com
 *
 **/
class PageimageRemoveVariationsConfig extends Wire
{

    public $excludeList;

    public function getConfig(array $data)
    {

        // check that they have the required PW version
        if (version_compare(wire('config')->version, '2.4.0', '<')) {
            $this->error(" requires ProcessWire 2.4.0 or newer. Please update.");
        }

        $modules = wire('modules');
        $form = new InputfieldWrapper();

        $field = $modules->get("InputfieldMarkup");
        $field->attr('name', 'info1');
        $field->collapsed = Inputfield::collapsedNo;
        $field->attr('value',
            "This module may run a cleaning script that removes all Imagevariations of all pages, sitewide! To do that you have to tipp the checkbox below and submit the form.
            Regarding to the amount of pages and images you have installed in your site this can take some time.<br /><br />
            WARNING: This script will also delete Imagevariations used in RTEs, what you would to avoid, because those images are not recreated automatically! So be careful and doublecheck that you do not have those in use!<br /><br />"
        );
        $field->label = __('Info');
        $field->columnWidth = 100;
        $form->add($field);

        // exclude list
        $field = wire('modules')->get('InputfieldTextarea');
        $field->name = 'prv_exclude_list';
        $field->label = __("Exclude list");
        $field->value = isset($data['prv_exclude_list']) ? $data['prv_exclude_list'] : '';
        $field->description = __('Fields to skip during the removal process. Enter one field name per line. Prefix with "//" to comment out items.');
        if (isset($data['prv_exclude_list'])) $field->value = $data['prv_exclude_list'];
        $form->add($field);

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'remove_all_variations');
        $field->label = __('Remove all Imagevariations to clear the images-cache sitewide!');
        $field->attr('value', 1);
        $field->attr('checked', '');
        $field->columnWidth = 65;
        $form->add($field);

        if (wire('session')->remove_all_variations) {
            wire('session')->remove('remove_all_variations');
            $testmode = '1' == $data['do_only_test_run'] ? true : false;

            // build excludeList array
            $excludeList = trim($data['prv_exclude_list']);
            $excludeList = explode("\n", $excludeList);
            $excludeList = array_filter($excludeList, 'trim');
            $this->excludeList = array_filter($excludeList, array($this, 'removeCommentedItems'));

            $field->notes = $this->doTheDishes(!$testmode);

        } else if (wire('input')->post->remove_all_variations) {
            wire('session')->set('remove_all_variations', 1);
        }

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'do_only_test_run');
        $field->label = __('Run only in test mode! Do not delete the variations.');
        $field->attr('value', 1);
        $field->attr('checked', '1');
        $field->columnWidth = 35;
        $form->add($field);

        return $form;
    }

    // skip items starting with "//" (commented out)
    public function removeCommentedItems($item)
    {
        return substr($item, 0, 2) !== '//';
    }


    public function doTheDishes($deleteVariations = false)
    {
        $errors = array();
        $success = false;
        try {
            $success = $this->removeAllVariations($deleteVariations);

        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
        if ($success) {
            $note = $deleteVariations ?
                $this->_('SUCCESS! All Imagevariations are removed.') :
                $this->_('SUCCESS! Found and listed all Pages with Imagevariations.');
            $this->message($note);

        } else {
            $note = $deleteVariations ?
                $this->_('ERROR: Removing Imagevariations was not successfully finished. Refer to the error log for more details.') :
                $this->_('ERROR: Could not find and list all Pages containing Imagevariations. Refer to the error log for more details.');
            $this->error($note);
        }
        return $note;
    }


    private function removeAllVariations($deleteVariations = false)
    {
        $stack = new Filo();
        $stack->push(1);
        while ($id = $stack->pop()) {
            set_time_limit(intval(15));
            // get the page
            $page = wire('pages')->get($id);
            if (0 == $page->id) continue;
            // add children to the stack
            foreach ($page->children('include=all') as $child) {
                $stack->push($child->id);
            }

            // iterate over the fields
            foreach ($page->fields as $field) {

                // process only Image fields
                if (!$field->type instanceof FieldtypeImage) {
                    continue;
                }

                // skip if field is on the exclude list
                if (in_array($field->name, $this->excludeList) !== false) {
                    continue;
                }

                // get the images
                $imgs = $page->{$field->name};
                $count = count($imgs);
                if (0 == $count) continue;
                $this->message('- found page: ' . $page->title . ' - with imagefield: ' . $field->name . ' - count: ' . $count);
                foreach ($imgs as $img) {
                    if (true === $deleteVariations) {
                        #$this->message(' REMOVED! ');
                        $img->removeVariations();
                    }
                }
            }
            wire('pages')->uncache($page);
        }
        return true;
    }

}

if (!class_exists('Filo')) {
    /** @shortdesc: Stack, First In - Last Out  * */
    class Filo
    {

        /** @private * */
        var $elements;
        /** @private * */
        var $debug;

        /** @private * */
        function __construct($debug = false)
        {
            $this->debug = $debug;
            $this->zero();
        }

        /** @private * */
        function push($elm)
        {
            array_push($this->elements, $elm);
            if ($this->debug) echo "<p>Filo->push(" . $elm . ")</p>";
        }

        /** @private * */
        function pop()
        {
            $ret = array_pop($this->elements);
            if ($this->debug) echo "<p>Filo->pop() = $ret</p>";
            return $ret;
        }

        /** @private * */
        function zero()
        {
            $this->elements = array();
            if ($this->debug) echo "<p>Filo->zero()</p>";
        }
    }
} // end class Filo

