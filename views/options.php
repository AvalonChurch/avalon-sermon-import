<?php
class SermonManagerImportSettings
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Default import settings
     */
    private $defaults = array(
        'sermon_title' => 'title',
        'date' => 'subtitle',
        'preacher' => 'artist',
        'sermon_series' => 'album',
        'sermon_topics' => 'genre',
        'sermon_description' => 'comments',
        'bible_passage' => 'composer',
        'publish_status' => 'publish',
        'bible_book_series' => '0',
        'upload_folder' => 'sermon-manager-import'
    );

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_options_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_options_page()
    {
        // This page will be under "Settings"
        add_submenu_page(
            'edit.php?post_type=wpfc_sermon', 
            __('Sermon Manager Import Settings', 'sermon-manager-import'), 
            __('Import Settings', 'sermon-manager-import'), 
            'manage_options', 
            __FILE__, 
            array( $this, 'create_admin_page')
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = wp_parse_args(get_option( 'smi_options' ), $this->defaults);
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2>Sermon Manager Import Settings</h2>           
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'smi_options' );   
                do_settings_sections( 'smi-settings' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'smi_options', // Option group
            'smi_options', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_import', // ID
            'ID3 Tag Mapping', // Title
            array( $this, 'print_section_info_import' ), // Callback
            'smi-settings' // Page
        );  

        add_settings_field(
            'sermon_title', 
            'Sermon Title', 
            array( $this, 'settings_option_callback' ), 
            'smi-settings', 
            'setting_section_import',
            array (
                'sermon_title'
            )
        );

        add_settings_field(
            'date', 
            'Date', 
            array( $this, 'settings_option_callback' ), 
            'smi-settings', 
            'setting_section_import',
            array (
                'date'
            )
        );

        add_settings_field(
            'preacher', 
            'Preacher', 
            array( $this, 'settings_option_callback' ), 
            'smi-settings', 
            'setting_section_import',
            array (
                'preacher'
            )
        );

        add_settings_field(
            'sermon_series', 
            'Sermon Series', 
            array( $this, 'settings_option_callback' ), 
            'smi-settings', 
            'setting_section_import',
            array (
                'sermon_series'
            )
        );

        add_settings_field(
            'sermon_topics', 
            'Sermon Topics', 
            array( $this, 'settings_option_callback' ), 
            'smi-settings', 
            'setting_section_import',
            array (
                'sermon_topics'
            )
        );

        add_settings_field(
            'sermon_description', 
            'Sermon Description', 
            array( $this, 'settings_option_callback' ), 
            'smi-settings', 
            'setting_section_import',
            array (
                'sermon_description'
            )
        );

        add_settings_field(
            'bible_passage', 
            'Bible Passage', 
            array( $this, 'settings_option_callback' ), 
            'smi-settings', 
            'setting_section_import',
            array (
                'bible_passage'
            )
        );
        
        add_settings_field(
            'publish_status', 
            'Publish Status', 
            array( $this, 'publish_status_callback' ), 
            'smi-settings', 
            'setting_section_import',
            array (
                'publish_status'
            )
        );

        add_settings_field(
            'bible_book_series', 
            'Bible book series', 
            array( $this, 'bible_book_series_callback' ), 
            'smi-settings', 
            'setting_section_import',
            array (
                'bible_book_series'
            )
        );

        add_settings_section(
            'setting_section_other',
            'Import Settings',
            array( $this, 'print_section_info_other' ),
            'smi-settings'
        );

        add_settings_field(
            'upload_folder', 
            'Upload folder name', 
            array( $this, 'upload_folder_callback' ), 
            'smi-settings', 
            'setting_section_other',
            array (
                'upload_folder'
            )
        );
    
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        foreach ($input as $key => $opt) {
           if( !empty( $input[$key] ) )
            $input[$key] = sanitize_text_field( $input[$key] );
        }
        
        return $input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info_import()
    {
        print 'Select an ID3 tag for each Sermon property.';
    }

    /** 
     * Print the Section text
     */
    public function print_section_info_other()
    {
        print 'Other settings';
    }

    /** 
     * Publish setting from option array
     */
    public function publish_status_callback($args)
    {
        $selected = esc_attr( $this->options[$args[0]]);

        $options = '<select id="'. $args[0] . '" name="smi_options[' .  $args[0] .']">
            <option value="publish"' . selected($selected, 'publish', false) . '>Publish</option>
            <option value="draft"' . selected($selected, 'draft', false) . '>Draft</option>
        </select>';

        echo $options;
    }

    /** 
     * Get the settings option array for ID3 tag mapping
     */
    public function settings_option_callback($args)
    {
        $selected = esc_attr( $this->options[$args[0]]);

        $options = '<select id="'. $args[0] . '" name="smi_options[' .  $args[0] .']">
            <option value="title"' . selected($selected, 'title', false) . '>Title</option>
            <option value="subtitle"' . selected($selected, 'subtitle', false) . '>Subtitle</option>
            <option value="artist"' . selected($selected, 'artist', false) . '>Artist</option>
            <option value="album"' . selected($selected, 'album', false) . '>Album</option>
            <option value="genre"' . selected($selected, 'genre', false) . '>Genre</option>
            <option value="comments"' . selected($selected, 'comments', false) . '>Comments</option>
            <option value="composer"' . selected($selected, 'composer', false) . '>Composer</option>
            <option value="picture"' . selected($selected, 'picture', false) . '>Attached Picture</option>
            <option value="year"' . selected($selected, 'year', false) . '>Year</option>
            <option value=""' . selected($selected, '', false) . '>Not set</option>
        </select>';

        echo $options;
    }

    /**
     * Use the bible book from the bible passage as the series
     */
    public function bible_book_series_callback($args)
    {
        echo '<input type="checkbox" id="'.$args[0].'" name="smi_options['.$args[0].']"  value="1" '. checked( 1, esc_attr( $this->options[$args[0]]), false ) . '" /> Uses the bible book from the bible passage as the series name.';
    }

    public function upload_folder_callback($args)
    {
        $uploads_details = wp_upload_dir();
        echo '<input type="text" id="'.$args[0].'" name="smi_options['.$args[0].']" value="'.$this->options[$args[0]].'" class="regular-text"> <br />Your upload path is '.$uploads_details['basedir'].'/'.$this->options[$args[0]];
    }

}

if( is_admin() )
    $sermon_manager_import_settings = new SermonManagerImportSettings();

//sdg
