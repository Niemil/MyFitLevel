package com.myfitlevel.app

import android.appwidget.AppWidgetManager
import android.content.Intent
import android.graphics.Color
import android.graphics.drawable.GradientDrawable
import android.os.Bundle
import android.view.Gravity
import android.view.View
import android.widget.Button
import android.widget.FrameLayout
import android.widget.HorizontalScrollView
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.ScrollView
import android.widget.SeekBar
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity

/** Lets you pick the look of one widget: theme, accent colour, corners, see-through level and (for some) what it shows. */
class WidgetConfigActivity : AppCompatActivity() {
    private var wid = AppWidgetManager.INVALID_APPWIDGET_ID
    private lateinit var st: Style
    private lateinit var preview: FrameLayout
    private lateinit var pTitle: TextView
    private lateinit var pSub: TextView
    private lateinit var pBar: ProgressBar
    private lateinit var pctLabel: TextView
    private var theme = 0; private var accent = 0; private var opacity = 92; private var radius = 1; private var feed = 0; private var boss = 0

    private fun dp(v: Int) = (v * resources.displayMetrics.density).toInt()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setResult(RESULT_CANCELED)
        wid = intent?.extras?.getInt(AppWidgetManager.EXTRA_APPWIDGET_ID, AppWidgetManager.INVALID_APPWIDGET_ID) ?: AppWidgetManager.INVALID_APPWIDGET_ID
        if (wid == AppWidgetManager.INVALID_APPWIDGET_ID) { finish(); return }
        val cn = AppWidgetManager.getInstance(this).getAppWidgetInfo(wid)?.provider?.className ?: ""
        val prov = try { Class.forName(cn).getDeclaredConstructor().newInstance() as BaseWidget } catch (e: Exception) { null }
        val s0 = Cfg.get(this, wid, prov?.defaultStyle ?: Style(0, 0, 92, 1, 0, 0))
        theme = s0.theme; accent = s0.accent; opacity = s0.opacity; radius = s0.radius; feed = s0.feed; boss = s0.boss
        val showFeed = cn.endsWith("FeedWidget") || cn.endsWith("FeedSmallWidget")
        val showBoss = cn.endsWith("BossWidget") || cn.endsWith("BundleWidget")

        val col = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL; setPadding(dp(16), dp(24), dp(16), dp(24)) }
        fun label(t: String, size: Float = 14f, bold: Boolean = false) = TextView(this).apply {
            text = t; textSize = size; setTextColor(0xFFE6EAF0.toInt()); setPadding(0, dp(12), 0, dp(4)); if (bold) setTypeface(typeface, android.graphics.Typeface.BOLD)
        }
        col.addView(label("Widget settings", 22f, true))
        col.addView(label("Pick a look. You can change it later (long-press the widget on Android 12+, or tap the gear on it).", 13f))

        preview = FrameLayout(this).apply { setPadding(dp(16), dp(14), dp(16), dp(14)) }
        val inner = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL }
        pTitle = TextView(this).apply { text = "Level 26"; textSize = 18f; setTypeface(typeface, android.graphics.Typeface.BOLD) }
        pSub = TextView(this).apply { text = "53,065 points · 3,185 to next level"; textSize = 12f }
        pBar = ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal).apply { max = 100; progress = 25 }
        inner.addView(pTitle); inner.addView(pSub); inner.addView(pBar, LinearLayout.LayoutParams(-1, dp(10)).apply { topMargin = dp(8) })
        preview.addView(inner)
        col.addView(preview, LinearLayout.LayoutParams(-1, -2).apply { topMargin = dp(10) })

        fun choice(title: String, opts: List<String>, sel: Int, colors: IntArray? = null, onPick: (Int) -> Unit) {
            col.addView(label(title, 13f, true))
            val row = LinearLayout(this).apply { orientation = LinearLayout.HORIZONTAL }
            val views = ArrayList<TextView>()
            fun paint() { for ((i, tv) in views.withIndex()) { tv.alpha = 1f; tv.setBackgroundColor(if (tv.tag == true) 0xFF3A4660.toInt() else 0x00000000) } }
            var cur = sel
            for ((i, o) in opts.withIndex()) {
                val tv = TextView(this).apply {
                    text = if (colors != null) "●" else o
                    textSize = if (colors != null) 28f else 14f
                    setTextColor(if (colors != null) colors[i] else 0xFFE6EAF0.toInt())
                    setPadding(dp(14), dp(8), dp(14), dp(8)); gravity = Gravity.CENTER
                    tag = (i == sel)
                }
                views.add(tv)
                tv.setOnClickListener { cur = i; for ((j, v2) in views.withIndex()) { v2.tag = (j == i) }; paint(); onPick(i) }
                row.addView(tv, LinearLayout.LayoutParams(-2, -2).apply { marginEnd = dp(6) })
            }
            paint()
            col.addView(HorizontalScrollView(this).apply { addView(row); isHorizontalScrollBarEnabled = false })
        }
        choice("Theme", Themes.list.map { it.name }, theme) { theme = it; refreshPreview() }
        choice("Accent colour", Themes.accentNames, accent, Themes.accents) { accent = it; refreshPreview() }
        choice("Corners", Themes.corners, radius) { radius = it; refreshPreview() }
        if (showFeed) choice("Show", listOf("Everyone", "Only me"), feed) { feed = it }
        if (showBoss) choice("Boss", listOf("Weekly boss", "Team raid"), boss) { boss = it }
        pctLabel = label("See-through: ", 13f, true)
        col.addView(pctLabel)
        col.addView(SeekBar(this).apply {
            max = 100; progress = opacity
            setOnSeekBarChangeListener(object : SeekBar.OnSeekBarChangeListener {
                override fun onProgressChanged(sb: SeekBar, p: Int, u: Boolean) { opacity = maxOf(0, p); refreshPreview() }
                override fun onStartTrackingTouch(sb: SeekBar) {}
                override fun onStopTrackingTouch(sb: SeekBar) {}
            })
        })
        col.addView(Button(this).apply {
            text = "Save"
            setOnClickListener {
                Cfg.save(this@WidgetConfigActivity, wid, Style(theme, accent, opacity, radius, feed, boss))
                Widgets.updateOne(this@WidgetConfigActivity, wid)
                setResult(RESULT_OK, Intent().putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, wid))
                finish()
            }
        }, LinearLayout.LayoutParams(-1, -2).apply { topMargin = dp(20) })
        setContentView(ScrollView(this).apply { addView(col) })
        refreshPreview()
    }

    private fun refreshPreview() {
        val s = Style(theme, accent, opacity, radius, feed, boss)
        val bgc = (s.bg and 0x00FFFFFF) or ((opacity * 255 / 100) shl 24)
        preview.background = GradientDrawable().apply { setColor(bgc); cornerRadius = dp(listOf(10, 18, 28)[radius.coerceIn(0, 2)]).toFloat(); setStroke(dp(1), 0x33FFFFFF) }
        pTitle.setTextColor(s.text); pSub.setTextColor(s.sub)
        pBar.progressTintList = android.content.res.ColorStateList.valueOf(s.acc)
        pBar.progressBackgroundTintList = android.content.res.ColorStateList.valueOf(s.track)
        pctLabel.text = "Background opacity: $opacity%"
    }
}
