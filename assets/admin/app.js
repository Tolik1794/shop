/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
// import './styles/app.css';

// require jQuery normally
const $ = require('jquery');

//add global variables
global.$ = global.jQuery = require('jquery');
// Import css files
import 'select2/dist/css/select2.min.css';

// import js files
import 'jquery'
import 'select2/dist/js/select2.full';
import '../theme/admin_kit/js/app';
import '../js/font-awesome'
// import '../js/select2-init'

// start the Stimulus application
import '../bootstrap';
