import './bootstrap';

import Alpine from 'alpinejs';
import stockLines from './stock-lines';
import quotationLines from './quotation-lines';

window.Alpine = Alpine;

Alpine.data('stockLines', stockLines);
Alpine.data('quotationLines', quotationLines);

Alpine.start();
