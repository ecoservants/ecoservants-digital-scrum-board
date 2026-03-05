import { render } from '@wordpress/element';
import ScrumBoard from './components/ScrumBoard';
import { applyTheme, loadTheme } from "./utils/themeManager";


fetch("/wp-json/es-scrum/v1/user-profile")
    .then(res => res.json())
    .then(data => {
        if (data.theme) {
            applyTheme(data.theme);
        }
    });
    

const savedTheme = loadTheme();
applyTheme(savedTheme);

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('es-scrum-board-app');
    if (container) {
        if (container.innerHTML !== '') {
            container.innerHTML = '';
        }
        render(<ScrumBoard />, container);
    }
});