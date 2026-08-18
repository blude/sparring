/*
 * Shared juiciness on/off helper. window.JUICY is set per-page (dojo.php /
 * arena.php) from config.php's JUICY_* constants, so every effect can be
 * killed globally (JUICY_ENABLED) or individually (JUICY_PUNCH, JUICY_SOUND,
 * ...) with a one-line edit in config.php — no code change, no redeploy step
 * beyond that file.
 */
window.isJuicyOn = function (effect) {
    var j = window.JUICY || {};
    if (j.enabled === false) return false;
    return j[effect] !== false; // fails open: unset key defaults on, matching window.X || default elsewhere
};
