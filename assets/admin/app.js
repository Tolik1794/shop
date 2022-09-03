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

// import js files
import '../theme/admin_kit/js/app';
import 'jquery'

// start the Stimulus application
import '../bootstrap';
