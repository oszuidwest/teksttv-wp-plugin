import Alpine from 'alpinejs';
import { registerTeksttvAlpine } from './alpine/register';
import { guardUnderscoreForMedia } from './modules/wpMedia';

guardUnderscoreForMedia();

registerTeksttvAlpine();
Alpine.start();
