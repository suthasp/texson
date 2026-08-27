import './bootstrap';

import Alpine from 'alpinejs';
import stockLines from './stock-lines';

window.Alpine = Alpine;

Alpine.data('stockLines', stockLines);

Alpine.start();
