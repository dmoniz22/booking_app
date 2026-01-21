# Directive: Scaffold WordPress Plugin

**Goal**: Initialize a new WordPress plugin directory with standard boilerplate files (loader, activator, deactivator, assets).

**Inputs**:
- `plugin_name`: Human readable name (e.g., "My Booking Plugin").
- `plugin_slug`: Hyphenated identifier (e.g., "my-booking-plugin").
- `description`: Short description.
- `author`: Author name.
- `destination`: Target directory (usually `wp-content/plugins/` but defaults to current workspace for development).

**Tools**:
- `execution/scaffold_wp_plugin.py`

**Protocol**:

1.  **Validation**:
    - Ensure `plugin_slug` contains only lowercase letters and hyphens.
    - Check if destination directory already exists.

2.  **Execution**:
    - Run the scaffold script:
      ```bash
      python execution/scaffold_wp_plugin.py \
        --name "[plugin_name]" \
        --slug "[plugin_slug]" \
        --description "[description]" \
        --author "[author]" \
        --dest "[destination]"
      ```

3.  **Output Verification**:
    - Confirm the following structure exists:
      ```text
      [slug]/
      ├── [slug].php
      ├── readme.txt
      ├── includes/
      │   ├── class-[slug]-loader.php
      │   ├── class-[slug]-activator.php
      │   └── class-[slug]-deactivator.php
      ├── admin/
      │   ├── class-[slug]-admin.php
      │   ├── css/
      │   └── js/
      └── public/
          ├── class-[slug]-public.php
          ├── css/
          └── js/
      ```

4.  **Error Handling**:
    - If directory exists, abort with error unless `--force` is implied (not currently supported by script).
