/*
 * Shared visitor identity: alias + avatar emoji, both pure functions of
 * sessionId. No storage, no server round-trip — deterministic so the same
 * session always gets the same alias/avatar, on the input header and on
 * the display wall alike.
 *
 * Alias scheme (naive, by design): [prefix][role] [proper name] [number],
 * each picked independently off the seed. E.g. "Kegakusha Haburamu Jūyongō".
 */
window.SparringIdentity = (function () {
    'use strict';

    const jiraiyaPrefixes = [
        "Oni",      // 鬼 - ogre/demon
        "Chō",      // 蝶 - butterfly
        "Hoshi",    // 星 - star
        "Tori",     // 鳥 - bird
        "Yō",       // 妖 - witch/spirit
        "Kaze",     // 風 - wind
        "Uchū",     // 宇宙 - space
        "Shiro",    // 城 - castle
        "Rō",       // 牢 - jail/prison
        "Igyō",     // 異形 - grotesque/strange form
        "Baku",     // 爆 - explosive
        "Rai",      // 雷 - lightning/thunder
        "Sei",      // 聖 - holy/sacred
        "Kami",     // 紙 - paper
        "Hana",     // 花 - flower
        "Hō",       // 宝 - treasure
        "Hi",       // 火 - fire
        "Kan",      // 漢 - Chinese/Han
        "Jū",       // 獣 - beast
        "Gō",       // 剛 - strong/tough
        "Oto",      // 音 - sound
        "Matsuri",  // 祭 - feast/festival
        "Tetsu",    // 鉄 - metal/iron
        "Sui",      // 水 - water
        "Ke",       // 化 - changing/transformation
        "Yami",     // 闇 - darkness
        "Shaku",    // 灼 - burning/scorching
        "Ma",       // 魔 - demon/magic
        "Ja",       // 邪 - evil/wicked
    ];

    const japaneseRoles = [
        "ninja",        // 忍者 - ninja
        "samurai",      // 侍 - samurai
        "bushi",        // 武士 - warrior
        "karateka",     // 空手家 - karate practitioner
        "kenshi",       // 剣士 - swordsman
        "kyūdōka",      // 弓道家 - archer (kyudo practitioner)
        "rikishi",      // 力士 - sumo wrestler
        "heishi",       // 兵士 - soldier
        "yōhei",        // 傭兵 - mercenary
        "kishi",        // 騎士 - knight

        "odoriko",      // 踊り子 - dancer (traditional)
        "dansā",        // ダンサー - dancer (modern)
        "geisha",       // 芸者 - geisha
        "yakusha",      // 役者 - actor
        "kashu",        // 歌手 - singer
        "ongakuka",     // 音楽家 - musician
        "kyokugeishi",  // 曲芸師 - acrobat
        "tejinashi",    // 手品師 - magician/illusionist
        "rakugoka",     // 落語家 - rakugo storyteller

        "shokunin",     // 職人 - craftsman/artisan
        "kajiya",       // 鍛冶屋 - blacksmith
        "daiku",        // 大工 - carpenter
        "ryōshi",       // 漁師 - fisherman
        "nōmin",        // 農民 - farmer
        "shōnin",       // 商人 - merchant
        "isha",         // 医者 - doctor
        "gakusha",      // 学者 - scholar
        "sōryo",        // 僧侶 - monk/priest
        "miko",         // 巫女 - shrine maiden

        "mahōtsukai",   // 魔法使い - sorcerer/witch
        "onmyōji",      // 陰陽師 - esoteric diviner
        "uranaishi",    // 占い師 - fortune teller
        "yamabushi",    // 山伏 - mountain ascetic
        "tōzoku",       // 盗賊 - thief/bandit
        "ansatsusha",   // 暗殺者 - assassin
    ];

    const jiraiyaProperNames = [
        "Dokusai",          // 毒斎
        "Benikiba",         // 紅牙
        "Retsukiba",        // 烈牙
        "Karasu-Tengu",     // カラス天狗
        "Kumo-Gozen",       // クモ御前
        "Mafūba",           // 馬風破
        "Gōma",             // ゴーマ
        "Demosuto",         // デモスト
        "Fukurō Danshaku",  // フクロウ男爵
        "Haburamu",         // ハブラム
        "Benitokage",       // 紅トカゲ
        "Rocket Man",       // ロケットマン
        "Wild",             // ワイルド
        "Aramūsa",          // アラムーサ
        "Oruha",            // 折破
        "Yumeha",           // 夢破
        "Jeanne",           // ジャンヌ
        "Chan Kung-Fu",     // チャンカンフー
        "Ryokuryū",         // 緑龍
        "Macumba",          // マクンバ
        "Abudada",          // アブダダ
        "Uha",              // 宇破
        "Gyūma",            // ギュウマ
        "Gamesshu",         // ガメッシュ
        "Silver Shark",     // シルバーシャーク
        "Parchisu",         // パルチス
        "Devil Cats",       // デビルキャッツ
        "Sutorōbo",         // ストローボ
        "Sylvia",           // シルビア
        "Kurozaru",         // 黒猿
        "Maō",              // 魔王
        "Sugitani",         // 杉谷
        "Kuroi Ibara",      // 黒い茨
    ];

    const japaneseNumbers = [
        "Ichigō",     // 一号 - No. 1
        "Nigō",       // 二号 - No. 2
        "Sangō",      // 三号 - No. 3
        "Yongō",      // 四号 - No. 4
        "Gogō",       // 五号 - No. 5
        "Rokugō",     // 六号 - No. 6
        "Nanagō",     // 七号 - No. 7
        "Hachigō",    // 八号 - No. 8
        "Kyūgō",      // 九号 - No. 9
        "Jūgō",       // 十号 - No. 10
        "Jūichigō",   // 十一号 - No. 11
        "Jūnigō",     // 十二号 - No. 12
        "Jūsangō",    // 十三号 - No. 13
        "Jūyongō",    // 十四号 - No. 14
        "Jūgogō",     // 十五号 - No. 15
        "Jūrokugō",   // 十六号 - No. 16
        "Jūnanagō",   // 十七号 - No. 17
        "Jūhachigō",  // 十八号 - No. 18
        "Jūkyūgō",    // 十九号 - No. 19
        "Nijūgō",     // 二十号 - No. 20
    ];

    var AVATARS = ['🐣', '🦊', '🐼', '🐸', '🐢', '🦉', '🐙', '🐿️', '🦔', '🐝', '🦋', '🐳', '🦕', '🐧', '🐨', '🦄', '🦆', '🐲'];

    function seed(sessionId) {
        // sessionId is Crockford Base32 (Store.php newSessionId) — not hex, so
        // parseInt(id, 16) would stop at the first non-hex char (G,H,J,K,M,N,P,
        // Q,R,S,T,V,W,X,Y,Z), collapsing >50% of sessions to the same seed.
        // Hash every char instead of assuming a hex-compatible alphabet.
        var h = 0;
        for (var i = 0; i < sessionId.length; i++) {
            h = (h * 31 + sessionId.charCodeAt(i)) >>> 0; // >>> 0 keeps it a positive 32-bit int
        }
        return h;
    }

    function alias(sessionId) {
        var s = seed(sessionId);
        // mixed-radix digit extraction: treat s as a number in base
        // [P, R, N, Num] and read off one "digit" per pick, each stepped
        // by the product of prior list lengths — same digit as base-10
        // "ones/tens/hundreds", so no pick reads the same slice of s twice.
        var prefix = jiraiyaPrefixes[s % jiraiyaPrefixes.length];
        var role = japaneseRoles[Math.floor(s / jiraiyaPrefixes.length) % japaneseRoles.length];
        var properName = jiraiyaProperNames[Math.floor(s / (jiraiyaPrefixes.length * japaneseRoles.length)) % jiraiyaProperNames.length];
        var number = japaneseNumbers[Math.floor(s / (jiraiyaPrefixes.length * japaneseRoles.length * jiraiyaProperNames.length)) % japaneseNumbers.length];
        return prefix + role + ' ' + properName + ' ' + number; // naive, by design: [prefix][role] [proper name] [number]
    }

    function avatar(sessionId) {
        var s = seed(sessionId);
        return AVATARS[Math.floor(s / 7) % AVATARS.length]; // different stride, decorrelates from alias pick
    }

    return { alias: alias, avatar: avatar };
})();
