import Alpine from 'alpinejs';
import { registerTeksttvAlpine } from './alpine/register';
import { initDirtyFormGuards } from './modules/dirtyForms';
import { guardUnderscoreForMedia } from './modules/wpMedia';

guardUnderscoreForMedia();

registerTeksttvAlpine();
Alpine.start();
initDirtyFormGuards();
