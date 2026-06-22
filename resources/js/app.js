import './bootstrap';

import Alpine from 'alpinejs';
import { qrCopy } from './alpine-plugins';

window.Alpine = Alpine;
window.qrCopy = qrCopy;

Alpine.start();
