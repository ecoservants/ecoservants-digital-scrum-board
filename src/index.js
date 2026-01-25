import { render } from '@wordpress/element';
import ScrumBoard from './components/ScrumBoard';

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('es-scrum-board-app');
    if (container) {
        if (container.innerHTML !== '') {
            container.innerHTML = '';
        }
        render(<ScrumBoard />, container);
    }
});
