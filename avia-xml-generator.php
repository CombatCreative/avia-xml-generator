<?php
/**
 *  Plugin Name: Avia XML Generator
 *  Plugin URI: http://inoplugs.com
 *  Description: Generate XML files based on the post content
 *  Author: InoPlugs | Peter Schoenmann
 *  Version: 0.1
 *  Author URI: http://inoplugs.com
 */

	define( 'INO_GENXML_URLPATH', WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__ ),'',plugin_basename(__FILE__)) );
    define( 'INO_GENXML_URIPATH', plugin_dir_path( __FILE__ ) );

    require_once(INO_GENXML_URIPATH . 'config.php');

    global $avia_xml_config;
    $avia_changify = new avia_xml_gen($avia_xml_config);

    class avia_xml_gen
    {
        protected $avia_xml_settings;

        public function __construct($avia_xml_settings = NULL)
        {
            if(empty($avia_xml_settings)) return;
            $this->avia_xml_settings = $avia_xml_settings;

            add_action( 'save_post', array($this,'generate_xml_file') );
        }

        public function __destruct()
        {
            unset($this->avia_xml_settings);
        }


        public function generate_xml_file( $post_id )
        {
            $dir = $this->create_temp_xml_directory();

            if($dir === false) return;

            $post_categories = wp_get_post_categories( $post_id );

            if(!empty($post_categories) && !empty($this->avia_xml_settings))
            {
                foreach($post_categories as $post_category)
                {
                    if(isset($this->avia_xml_settings[$post_category]))
                    {
                        $matching_cats[] = $post_category;
                    }
                }
            }

            if(!empty($matching_cats))
            {
                foreach ($matching_cats as $matching_cat)
                {
                    $xmlcontent = array(); //this variable stores the xml data from the loop

                    if( !empty($this->avia_xml_settings[$matching_cat]['complete_changelog']) )
                    {
                        $args = array( 'category__in' => array($matching_cat), 'orderby' => 'date', 'order' => 'DESC' );
                    }
                    else
                    {
                        $args = array( 'post__in' => array($post_id) );
                    }

                    $xml_loop = new WP_Query($args);

                    while ( $xml_loop->have_posts() )
                    {

                        $xml_loop->the_post();

                        //get post data
                        $title = get_the_title();
                        $date = get_the_date();
                        $postcontent = get_the_content();
                        $id = get_the_ID();
                        $permalink = get_permalink();

                        foreach($this->avia_xml_settings[$matching_cat]['xml_nodes'] as $tag => $content)
                        {
                            $content = str_replace("{title}", $title, $content);
                            $content = str_replace("{date}", $date, $content);
                            $content = str_replace("{content}", $postcontent, $content);
                            $content = str_replace("{url}", $permalink, $content);


                            //replace post meta keys with values
                            $pattern = "/\{([^}]+)\}/";
                            preg_match_all($pattern, $content, $matches);

                            if(!empty($matches[1]))
                            {
                                $elementcount = 0;
                                foreach($matches[1] as $match)
                                {
                                    if (strpos($match,'postmeta_') !== false)
                                    {
                                        $meta_key = str_replace('postmeta_', '', $match);
                                        $fallback_value = '';
                                        $meta_value = '';

                                        //chech for fallback values (separated with |||)
                                        if (strpos($meta_key,'|||') !== false)
                                        {
                                            $pieces = explode("|||", $meta_key);
                                            $meta_key = $pieces[0];
                                            $fallback_value = $pieces[1];
                                        }

                                        $meta_value = get_post_meta($id, $meta_key, true);

                                        if(!empty($meta_value))
                                        {
                                            $content = str_replace($matches[0][$elementcount], $meta_value, $content);
                                        }
                                        else
                                        {
                                            if (!empty($fallback_value))
                                            {
                                                $fallback_value = str_replace("%title%", $title, $fallback_value);
                                                $fallback_value = str_replace("%date%", $date, $fallback_value);
                                                $fallback_value = str_replace("%content%", $postcontent, $fallback_value);
                                                $fallback_value = str_replace("%url%", $permalink, $fallback_value);
                                                $content = str_replace($matches[0][$elementcount], $fallback_value, $content);
                                            }
                                        }
                                    }

                                    $elementcount++;
                                }
                            }

                            if(!empty($id) && !empty($tag) && !empty($content)) $xmlcontent[$id][$tag] = $content;
                        }


                        //end while loop
                    }

                    wp_reset_query();

                    //get category name
                    $catname = get_cat_name( (int)$matching_cat );

                    //set xml file path
                    $xmlfile = trailingslashit( $dir ) . $catname . '.xml';
                    $xmlfile = apply_filters('avia_dyn_xml_file_path', $xmlfile);

                    $writer = new XMLWriter;
                    $writer->openMemory();
                    $writer->setIndent(true);
                    $writer->startDocument('1.0', 'UTF-8');


                    //declare it as an notifier document
                    $writer->startElement('notifier');
                    $writer->writeAttribute('version', '1.0');


                    //remove first element of the array because it's the latest post
                    $latestupdate = array_shift($xmlcontent);



                    //Write latest version element
                    $writer->startElement('LatestVersion');

                    foreach($latestupdate as $tag => $content)
                    {
                        $writer->writeElement($tag, $content);
                    }

                    //End latest version element
                    $writer->endElement();



                    //Write previous version element
                    $writer->startElement('PreviousVersions');

                    foreach($xmlcontent as $cat)
                    {
                        $writer->startElement('PreviousVersion');

                            foreach($cat as $tag => $content)
                            {
                                $writer->writeElement($tag, $content);
                            }

                        //End previous version element
                        $writer->endElement();
                    }

                    //End previous version element
                    $writer->endElement();

                    //End notifier element
                    $writer->endElement();

                    $writer->endDocument();
                    $content = $writer->outputMemory();

                $created = $this->create_file($xmlfile, $content, true);

                //end foreach loop
                }

            //end if matching cats
            }
        }



        /*
         * Helper functions
         */

        /*
        * creates a folder for the images
        */
        public function create_temp_xml_directory()
        {
            $wp_upload_dir  = wp_upload_dir();
            $xml_dir = $wp_upload_dir['basedir'].'/avia_xml';
            $xml_dir = str_replace('\\', '/', $xml_dir);
            $xml_dir = apply_filters('avia_xml_dir_path',  $xml_dir);

            $isdir = $this->create_folder($xml_dir);

            if($isdir === false)
            {
                return false;
            }
            else
            {
                return $xml_dir;
            }

        }

        public function create_folder(&$folder, $addindex = true)
        {
            if(is_dir($folder) && $addindex == false)
                return true;

            //      $oldmask = @umask(0);

            $created = wp_mkdir_p( trailingslashit( $folder ) );
            @chmod( $folder, 0777 );

            //      $newmask = @umask($oldmask);

            if($addindex == false) return $created;

            $index_file = trailingslashit( $folder ) . 'index.php';
            if ( file_exists( $index_file ) )
                return $created;

            $handle = @fopen( $index_file, 'w' );
            if ($handle)
            {
                fwrite( $handle, "<?php\r\necho 'Sorry, browsing the directory is not allowed!';\r\n?>" );
                fclose( $handle );
            }

            return $created;
        }

        public function create_file($file, $content = '', $verifycontent = true)
        {
            $handle = @fopen( $file, 'w' );
            if($handle)
            {
                $created = fwrite( $handle, $content );
                fclose( $handle );

                if($verifycontent === true)
                {
                    $handle = fopen($file, "r");
                    $filecontent = fread($handle, filesize($file));
                    $created = ($filecontent == $content) ? true : false;
                    fclose( $handle );
                }
            }
            else
            {
                $created  = false;
            }

            if($created !== false) $created = true;
            return $created;
        }
    }