<?php
/*
 * Config File for Avia XML Plugin
 * Structure of the config array is:
 *
 * {Category-ID} => array(
        'complete_changelog' => true, //add all posts of the category to the xml file - useful if you want to list all posts/changes in the change log (must be set to true or false )
        'xml_nodes' => array(
                       create a new array element for each xml node.
                       Use {title} to insert the post title and {date} to insert the date of the current post. Use {content} to insert the post content and {url} to insert the url.
                       Use {postmeta_META_VALUE_KEY} to insert a postmeta value. Ie if you want to print the content of the custom field with the name "update_message" use {postmeta_update_message}
                       You can also set a fallback value for the post meta field - if a field with the post meta key can't be found the text after the fallback separator ||| will be used. You can use %date% and %title% to insert the post publishing date or the post title, %content% to insert the content and %url% to insert the permalink
                       into thefallback value - i.e. {postmeta_testadd|||The post title is: %title% and the date: %date%} will return the meta value of the field testadd. If the field testadd doesn't exist the code will return the text "The post title is: %title% and the date: %date%" and %title% and %date% will be replaced dynamically.
                    //default tags (required are):
                    'version' => INO_GENIMG_URIPATH . 'images/template.png', //if you place the image into the plugin folder you can use the INO_GENIMG_URLPATH constant for the path to the plugin folder
                    'description' => 15, //default font size in px - you can overwrite it with the text line settings
                    'message' => 27,  //default line height in px - you can overwrite it with the text line settings
                    )
        )
 *
 */

global $avia_xml_config;

$avia_xml_config = array(
    54 => array(
        'complete_changelog' => true,
        'xml_nodes' => array(
            'version' => '{postmeta_version}',
            'url' => '{url}',
            'message' => '{content}'
        )
    )

);
