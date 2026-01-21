
import argparse
import os
import sys
import json
import re

def create_directory(path):
    if not os.path.exists(path):
        os.makedirs(path)

def write_file(path, content):
    with open(path, 'w') as f:
        f.write(content)

def to_class_name(slug):
    # my-plugin -> My_Plugin
    return slug.replace('-', '_').title().replace(' ', '_')

def main():
    parser = argparse.ArgumentParser(description='Scaffold a WordPress Plugin.')
    parser.add_argument('--name', required=True, help='Plugin Name')
    parser.add_argument('--slug', required=True, help='Plugin Slug')
    parser.add_argument('--description', required=True, help='Plugin Description')
    parser.add_argument('--author', required=True, help='Plugin Author')
    parser.add_argument('--dest', default='.', help='Destination Directory')
    
    args = parser.parse_args()
    
    # 1. Validation
    if not re.match(r'^[a-z0-9-]+$', args.slug):
        print(json.dumps({
            "status": "error",
            "message": "Slug must contain only lowercase letters, numbers, and hyphens."
        }))
        sys.exit(1)

    plugin_dir = os.path.join(args.dest, args.slug)
    
    if os.path.exists(plugin_dir):
        print(json.dumps({
            "status": "error",
            "message": f"Directory {plugin_dir} already exists."
        }))
        sys.exit(1)

    # 2. Structure Creation
    try:
        class_name = to_class_name(args.slug)
        
        # Directories
        admin_dir = os.path.join(plugin_dir, 'admin')
        public_dir = os.path.join(plugin_dir, 'public')
        includes_dir = os.path.join(plugin_dir, 'includes')
        
        create_directory(plugin_dir)
        create_directory(os.path.join(admin_dir, 'css'))
        create_directory(os.path.join(admin_dir, 'js'))
        create_directory(os.path.join(admin_dir, 'partials'))
        create_directory(os.path.join(public_dir, 'css'))
        create_directory(os.path.join(public_dir, 'js'))
        create_directory(os.path.join(public_dir, 'partials'))
        create_directory(includes_dir)

        # Main Plugin File
        main_file_content = f"""<?php
/**
 * Plugin Name:       {args.name}
 * Description:       {args.description}
 * Version:           1.0.0
 * Author:            {args.author}
 * Text Domain:       {args.slug}
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {{
	die;
}}

define( '{args.slug.upper().replace('-', '_')}_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 */
function activate_{args.slug.replace('-', '_')}() {{
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-{args.slug}-activator.php';
	{class_name}_Activator::activate();
}}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_{args.slug.replace('-', '_')}() {{
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-{args.slug}-deactivator.php';
	{class_name}_Deactivator::deactivate();
}}

register_activation_hook( __FILE__, 'activate_{args.slug.replace('-', '_')}' );
register_deactivation_hook( __FILE__, 'deactivate_{args.slug.replace('-', '_')}' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-{args.slug}.php';

function run_{args.slug.replace('-', '_')}() {{
	$plugin = new {class_name}();
	$plugin->run();
}}
run_{args.slug.replace('-', '_')}();
"""
        write_file(os.path.join(plugin_dir, f'{args.slug}.php'), main_file_content)

        # Activator
        activator_content = f"""<?php
class {class_name}_Activator {{
	public static function activate() {{
        // Activation logic
	}}
}}
"""
        write_file(os.path.join(includes_dir, f'class-{args.slug}-activator.php'), activator_content)

        # Deactivator
        deactivator_content = f"""<?php
class {class_name}_Deactivator {{
	public static function deactivate() {{
        // Deactivation logic
	}}
}}
"""
        write_file(os.path.join(includes_dir, f'class-{args.slug}-deactivator.php'), deactivator_content)

        # Main Class
        main_class_content = f"""<?php
class {class_name} {{
	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {{
        $this->plugin_name = '{args.slug}';
        $this->version = '1.0.0';
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
	}}

    private function load_dependencies() {{
        // Load loader, admin, public
    }}

    private function define_admin_hooks() {{
        // Admin hooks
    }}

    private function define_public_hooks() {{
        // Public hooks
    }}

    public function run() {{
        // Run loader
    }}
}}
"""
        write_file(os.path.join(includes_dir, f'class-{args.slug}.php'), main_class_content)
        
        # Readme
        readme_content = f"""=== {args.name} ===
Contributors: {args.author}
Tags: booking, schedule
Stable tag: 1.0.0
Requires at least: 5.0
Tested up to: 6.0
Requires PHP: 7.4
License: GPLv2 or later

{args.description}
"""
        write_file(os.path.join(plugin_dir, 'readme.txt'), readme_content)

        # Output Success
        print(json.dumps({
            "status": "success",
            "path": os.path.abspath(plugin_dir),
            "message": f"Plugin {args.name} created successfully."
        }))

    except Exception as e:
        print(json.dumps({
            "status": "error",
            "message": str(e)
        }))
        sys.exit(1)

if __name__ == '__main__':
    main()
