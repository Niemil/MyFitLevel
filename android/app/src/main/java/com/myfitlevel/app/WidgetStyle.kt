package com.myfitlevel.app

import android.content.Context
import android.graphics.Color

class Theme(val name: String, val bg: Int, val text: Int, val sub: Int)

object Themes {
    val list = listOf(
        Theme("Midnight", 0xFF161B26.toInt(), 0xFFF2F4F8.toInt(), 0xFF9AA5B5.toInt()),
        Theme("Black", 0xFF000000.toInt(), 0xFFFFFFFF.toInt(), 0xFFA0A0A8.toInt()),
        Theme("Light", 0xFFFFFFFF.toInt(), 0xFF14181F.toInt(), 0xFF5B6573.toInt()),
        Theme("Violet", 0xFF1E1442.toInt(), 0xFFFFFFFF.toInt(), 0xFFB7A9E6.toInt()),
        Theme("Forest", 0xFF0F2A22.toInt(), 0xFFEFFBF5.toInt(), 0xFF8FC4AE.toInt()),
        Theme("See-through", 0xFF0B1020.toInt(), 0xFFFFFFFF.toInt(), 0xFFD0D6E2.toInt()),
    )
    val accents = intArrayOf(0xFF7C6BFF.toInt(), 0xFF22D3C5.toInt(), 0xFFFF5CAA.toInt(), 0xFFFF9F43.toInt(), 0xFF4ADE80.toInt(), 0xFF4EA1FF.toInt(), 0xFFFFC53D.toInt())
    val accentNames = listOf("Indigo", "Teal", "Pink", "Orange", "Green", "Blue", "Yellow")
    val corners = listOf("Small", "Medium", "Large")
}

/** Look chosen for one widget on the home screen. */
class Style(val theme: Int, val accent: Int, val opacity: Int, val radius: Int, val feed: Int, val boss: Int) {
    val t get() = Themes.list[theme.coerceIn(0, Themes.list.size - 1)]
    val bg get() = t.bg
    val text get() = t.text
    val sub get() = t.sub
    val acc get() = Themes.accents[accent.coerceIn(0, Themes.accents.size - 1)]
    val track get() = Color.argb(60, Color.red(t.text), Color.green(t.text), Color.blue(t.text))
}

object Cfg {
    private fun sp(c: Context) = c.getSharedPreferences("mfl_widgets", Context.MODE_PRIVATE)
    fun get(c: Context, id: Int, def: Style): Style {
        val s = sp(c)
        return Style(s.getInt("$id.theme", def.theme), s.getInt("$id.accent", def.accent), s.getInt("$id.opacity", def.opacity),
            s.getInt("$id.radius", def.radius), s.getInt("$id.feed", def.feed), s.getInt("$id.boss", def.boss))
    }
    fun save(c: Context, id: Int, st: Style) {
        sp(c).edit().putInt("$id.theme", st.theme).putInt("$id.accent", st.accent).putInt("$id.opacity", st.opacity)
            .putInt("$id.radius", st.radius).putInt("$id.feed", st.feed).putInt("$id.boss", st.boss).apply()
    }
    fun clear(c: Context, id: Int) {
        val e = sp(c).edit()
        for (k in listOf("theme", "accent", "opacity", "radius", "feed", "boss")) e.remove("$id.$k")
        e.apply()
    }
}
