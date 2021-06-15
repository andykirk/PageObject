<?php
namespace PageObject;
/**
 * PageObject
 *
 * ...
 *
 * @package PageObject
 * @author Andy Kirk
 * @copyright Copyright (c) 2021
 * @version 0.1
 **/
#class PageObject implements \RecursiveIterator
class PageObject
{
    protected $parent        = null;
    
    protected $path          = '';
    protected $title         = '';
    protected $slug          = '';
    protected $site_title    = '';
    protected $body          = '';
    protected $body_data     = [];
    protected $children      = [];
    protected $children_map  = [];
    #protected $child_indices = [];
    #protected $index         = 0;

    protected function propToClassname($name = '', $type = '') {
        $m_name = $type . str_replace('_', '', ucfirst(strtolower($name)));

        return $m_name;
    }

    public function slugify($text) {
        // replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, '-');

        // remove duplicate -
        $text = preg_replace('~-+~', '-', $text);

        // lowercase
        $text = strtolower($text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }

    /*
    protected function setChildren($key, $value) {

        if ($key === false) {
            $this->children = $value;
        } elseif(empty($key)) {
            $this->children[] = $value;
        } else {
            $this->children[$key] = $value;
        }

        $this->child_indices = array_keys($this->children);

        return true;
    }
    */

    protected function setSlug($value) {
        $this->slug = $this->slugify($value);

        return true;
    }
    
    public function setParent(&$parent) {
        $parent->children[$this->path] = $this;
        $parent->setChildrenMap($this->path, $this);
        $this->parent =& $parent;
    }
    
    public function setChildrenMap($key, &$child) {
        $this->children_map[$key] =& $child;
            
        if (!empty($this->parent)) {
            $this->parent->setChildrenMap($key, $child);
        }
    }
    
    public function setChildren($key, &$value) {
        if ($key === false && is_array($value)) {
            foreach($value as $k => $v) {
                $this->setChildren($k, $v);
            }
        } else {
            $this->children[$key] = $value;
            $this->setChildrenMap($key, $value);
        }

        return true;
    }

    /*
        The idea is that we can define properties and get them without needing individual
        get methods, but we can also define specific getters if we need more processing.
        We can also provide a default to return if no value is found, for convenience:
        $thing = PageObject->get('nope', 'use-this');
        ($thing is now 'use-this')
    */
    public function &get($name, $default = false) {

        // If we've been passed an array, we expect to return a key/property of an array/object:
        if (is_array($name)) {
            $prop = $name[0];
            $key = $name[1];

            // Check if we've got a specific get method:
            $m_name = $this->propToClassname($prop, 'get');

            if (method_exists($this, $m_name)) {
                return $this->$m_name($key, $default);
            }

            if (property_exists($this, $prop)) {
                if (is_array($this->$prop)) {
                    if (array_key_exists($this->$prop, $key)) {

                        if (!empty($this->$prop[$key])) {
                            return $this->$prop[$key];
                        }

                        return $default;
                    }
                }

                if (is_object($this->$prop)) {
                    if (property_exists($this->$prop, $key)) {

                        if (!empty($this->$prop->$key)) {
                            return $this->$prop->$key;
                        }

                        return $default;
                    }
                }
            }

            return false;
        }

        $prop = $name;

        // Check if we've got a specific get method:
        $m_name = $this->propToClassname($prop, 'get');

        if (method_exists($this, $m_name)) {
            return $this->$m_name($default);
        }

        if (property_exists($this, $prop)) {
            if (!empty($this->$prop)) {
                return $this->$prop;
            }
        }
        
        return $default;
    }

    /*
        The idea is that we can define properties and set them without needing individual
        set methods, but we can also define specific setters if we need more processing.
    */
    public function set($name, $value) {
        // If we've been passed an array, we expect to return a key/property of an array/object:
        if (is_array($name)) {
            $prop = $name[0];
            $key = (isset($name[1]) ? $name[1] : false);

            // Check if we've got a specific get method:
            $m_name = $this->propToClassname($prop, 'set');

            if (method_exists($this, $m_name)) {
                return $this->$m_name($key, $value);
            }

            if (property_exists($this, $prop)) {
                if (is_array($this->$prop)) {
                    if ($key === false) {
                        $this->$prop = $value;
                    } elseif(empty($key)) {
                        $this->$prop[] = $value;
                    } else {
                        $this->$prop[$key] = $value;
                    }

                    return true;
                }

                if (is_object($this->$prop)) {
                    $this->$prop->$key = $value;
                    return true;
                }
            }

            return false;
        }

        $prop = $name;

        // Check if we've got a specific get method:
        $m_name = $this->propToClassname($prop, 'set');

        if (method_exists($this, $m_name)) {
            return $this->$m_name($value);
        }

        if (property_exists($this, $prop)) {
            return $this->$prop = $value;

        }

        return false;
    }

    public function getArrayPage($json = false) {
        $r = [
            'path'          => $this->path,
            'site_title'    => $this->site_title,
            'title'         => $this->title,
            'slug'          => $this->slug,
            'body'          => $this->body,
            'body_data'     => $this->body_data,
            'children'      => $this->getArrayChildren(),
            'children_map'  => $this->children_map
        ];

        if ($json) {
            return json_encode($r, true);
        } else {
            return $r;
        }
    }

    public function getArrayChildren() {
        $r = [];
        foreach ($this->children as $key => $child) {
            $r[$key] = $child->getArrayPage();
        }
        return $r;
    }

    /*
        I'm really not sure if this is the logical thing here.
        On the one hand you might expect 'page to string' to build a full HTML page, but that isn't
        what this class is about - it's about the data representing a page, so returning a data
        string (e.g. json) does make more sense in that light. BUT there are a load of string-based
        data formats out there, so still not sure.
    */
    public function __toString() {
        return $this->getPage(true);
    }

    /*
    public function current() {
        return $this->children[$this->child_indices[$this->index]];
    }

    public function next() {
        $this->index++;
    }

    public function key() {
        return $this->child_indices[$this->index];
    }

    public function valid() {
        $key_exists = isset($this->child_indices[$this->index]);
        if ($key_exists) {
            return isset($this->children[$this->child_indices[$this->index]]);
        }
        return false;
    }

    public function rewind() {
        $this->index = 0;
    }

    public function getChildren() {
        return new \RecursiveArrayIterator($this->children);
    }

    // Does this item have children?
    public function hasChildren() {
        return !empty($this->children);
    }
    */
}