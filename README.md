# Pages #

Custom pages for moodle, with multilangs, custom contexts, blocks, selectable layout, custom js and css for each page, easy url and more.

Using atto HTML editor to create pages in moodle.
- Create custom pages content from frontend.
- Customize your page with css and js only applied to this page.
- Add multilangs page contents.
- Select your prefered layout.
- Custom context for each page which allows adding blocks to each page individuly.
- Prebuild contact us page.
- Prebuild FAQ page.
- Add pages in navbar.
- Page content saved in cache for faster load.

For advanced users:
- Page content get rendered the same way mustache did so you can use {{config.}} or {{#str}} tags or anything else.

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/local/pg

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## License ##

2025 Mohammad Farouk <phun.for.physics@gmail.com>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
