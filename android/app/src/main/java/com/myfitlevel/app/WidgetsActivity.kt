package com.myfitlevel.app

import android.appwidget.AppWidgetManager
import android.content.ComponentName
import android.os.Bundle
import android.widget.Button
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity

/** Picture gallery of every widget, with one tap to put it on the home screen. */
class WidgetsActivity : AppCompatActivity() {
    private fun dp(v: Int) = (v * resources.displayMetrics.density).toInt()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val col = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL; setPadding(dp(16), dp(24), dp(16), dp(32)) }
        fun label(t: String, size: Float = 14f, bold: Boolean = false, color: Int = 0xFFE6EAF0.toInt()) = TextView(this).apply {
            text = t; textSize = size; setTextColor(color); setPadding(0, dp(6), 0, dp(4)); if (bold) setTypeface(typeface, android.graphics.Typeface.BOLD)
        }
        col.addView(label("Widgets", 24f, true))
        col.addView(label("Pick as many as you like. Each one can get its own colours, corners and see-through level, and every tap opens the right page in the app. The small ⟳ on a widget syncs your steps and reloads it; ⚙ changes its look.", 13f))
        col.addView(Button(this).apply { text = "Refresh all widgets now"; setOnClickListener { Widgets.refreshNow(this@WidgetsActivity); Toast.makeText(this@WidgetsActivity, "Refreshing...", Toast.LENGTH_SHORT).show() } })
        val items = listOf(
            Triple(BundleWidget::class.java, R.drawable.prev_bundle, "Everything" to "Big dashboard: level, steps, boss, week, chest and quick buttons."),
            Triple(LevelWidget::class.java, R.drawable.prev_level, "Level" to "Your level, progress ring and the way to the next level."),
            Triple(StepsWidget::class.java, R.drawable.prev_steps, "Steps" to "Steps today, progress to your goal and when they last synced."),
            Triple(BossWidget::class.java, R.drawable.prev_boss, "Boss" to "Weekly boss or team raid: HP, time left and your damage."),
            Triple(WeekWidget::class.java, R.drawable.prev_week, "Week and streak" to "Workout days against your weekly goal, plus your streak."),
            Triple(ChestWidget::class.java, R.drawable.prev_chest, "Daily chest" to "Know when the chest is ready, plus your coins."),
            Triple(ActionsWidget::class.java, R.drawable.prev_actions, "Quick buttons" to "Log, feed, boss, steps and chest in one row."),
            Triple(LogWidget::class.java, R.drawable.prev_log, "Log workout" to "One big button that opens the logger."),
            Triple(FeedWidget::class.java, R.drawable.prev_feed, "Feed" to "Latest from everyone, or only you."),
            Triple(FeedSmallWidget::class.java, R.drawable.prev_feedsmall, "Feed ticker" to "The two newest feed lines, small and quiet."),
            Triple(MiniWidget::class.java, R.drawable.prev_mini, "Minimal stats" to "Level, steps and streak. Looks best see-through."),
        )
        val mgr = AppWidgetManager.getInstance(this)
        for ((cls, img, info) in items) {
            col.addView(label(info.first, 16f, true).apply { setPadding(0, dp(20), 0, dp(2)) })
            col.addView(label(info.second, 12f, false, 0xFF9AA5B5.toInt()))
            col.addView(ImageView(this).apply { setImageResource(img); adjustViewBounds = true }, LinearLayout.LayoutParams(-1, -2).apply { topMargin = dp(6) })
            col.addView(Button(this).apply {
                text = "Add to home screen"
                setOnClickListener {
                    if (mgr.isRequestPinAppWidgetSupported) mgr.requestPinAppWidget(ComponentName(this@WidgetsActivity, cls), null, null)
                    else Toast.makeText(this@WidgetsActivity, "Long-press your home screen, tap Widgets and find MyFitLevel.", Toast.LENGTH_LONG).show()
                }
            })
        }
        setContentView(ScrollView(this).apply { addView(col) })
    }
}
