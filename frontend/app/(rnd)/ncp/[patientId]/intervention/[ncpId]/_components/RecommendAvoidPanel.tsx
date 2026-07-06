import { ThumbsUp, ThumbsDown, BookOpen } from "lucide-react";

interface GoalFoodGuide {
  recommend: string[];
  avoid: string[];
  generalRecommend: string;
  generalAvoid: string;
}

const GOAL_FOOD_GUIDES: Record<string, GoalFoodGuide> = {
  renal_diet: {
    recommend: ["Egg whites", "Chicken breast", "Apple", "Cabbage", "White rice"],
    avoid: ["Bananas", "Orange juice", "Milk / dairy", "Processed meats", "Dark colas"],
    generalRecommend:
      "Focus on high-quality low-phosphate protein sources. Distribute protein evenly across meals.",
    generalAvoid:
      "Avoid high-potassium and high-phosphate foods. Limit all dairy and whole grains in advanced CKD stages.",
  },
  diabetic_control: {
    recommend: ["Brown rice", "Oatmeal", "Bitter melon (ampalaya)", "Fish", "Leafy greens"],
    avoid: ["White bread", "Sugary drinks", "Fried foods", "Large white rice portions", "Sweetened condensed milk"],
    generalRecommend:
      "Prioritize low-glycemic index foods. Distribute carbohydrates evenly across 3 meals and 1–2 snacks.",
    generalAvoid:
      "Avoid refined sugars and high-GI carbohydrates. Limit saturated fats.",
  },
  cardiac_diet: {
    recommend: ["Oily fish (mackerel / sardines)", "Oatmeal", "Banana", "Kangkong", "Canola / olive oil"],
    avoid: ["Processed meats", "Salty dried fish (tuyo / daing)", "Canned goods", "Coconut milk (gata)", "Organ meats"],
    generalRecommend:
      "Follow DASH eating principles. Prioritize potassium-rich vegetables and omega-3 sources.",
    generalAvoid:
      "Strictly limit sodium intake. Avoid trans fats and saturated fats from processed foods.",
  },
  weight_loss: {
    recommend: ["Skinless chicken breast", "Tilapia", "Kangkong", "Papaya", "Brown rice (small portions)"],
    avoid: ["Fried foods", "Pork belly (liempo)", "Full-fat coconut milk", "White sugar", "Sweetened beverages"],
    generalRecommend:
      "Increase dietary fiber for satiety. Choose lean protein at every meal to preserve muscle mass.",
    generalAvoid:
      "Avoid calorie-dense, nutrient-poor foods. Limit portion sizes — especially starchy carbs.",
  },
  weight_gain: {
    recommend: ["Peanut butter", "Brown rice", "Eggs", "Chicken thigh", "Avocado"],
    avoid: [],
    generalRecommend:
      "Add energy-dense nutrient-rich foods between meals. Prioritize protein to support lean mass gain, not just fat.",
    generalAvoid:
      "Avoid filling up on low-calorie foods and fluids before meals. Do not skip meals.",
  },
  high_protein: {
    recommend: ["Chicken breast", "Eggs", "Tofu / tokwa", "Low-fat milk", "Fish"],
    avoid: ["Alcohol", "High-fat fried foods", "Empty-calorie snacks", "Excessive simple sugars"],
    generalRecommend:
      "Distribute protein evenly across all meals and snacks. Ensure adequate energy to prevent protein being used as fuel.",
    generalAvoid:
      "Avoid anything that increases metabolic stress. Do not under-eat energy — protein cannot do its repair work without adequate kcal.",
  },
  liver_disease: {
    recommend: ["Eggs", "Chicken breast", "Oatmeal", "Papaya", "Soft cooked vegetables"],
    avoid: ["Alcohol (absolute)", "Processed meats", "Salty condiments", "Raw shellfish"],
    generalRecommend:
      "Eat small frequent meals including a late-evening snack to prevent overnight catabolism. BCAA-rich foods preferred in encephalopathy.",
    generalAvoid:
      "Strictly avoid all alcohol. Limit sodium to control ascites. Never restrict protein long-term except during active severe encephalopathy.",
  },
  malnutrition: {
    recommend: ["Eggs", "Mung beans (monggo)", "Chicken lugaw (congee)", "Peanuts", "Evaporated milk"],
    avoid: [],
    generalRecommend:
      "Prioritize energy-dense foods in small frequent portions. Ensure thiamine-rich foods before refeeding protocol.",
    generalAvoid:
      "Avoid skipping meals or long fasting periods. Do not over-restrict any macronutrient.",
  },
};

interface Props {
  goalType: string | null;
}

export default function RecommendAvoidPanel({ goalType }: Props) {
  if (!goalType) return null;

  const guide = GOAL_FOOD_GUIDES[goalType];
  if (!guide) return (
    <div className="p-4 bg-warm-50 border border-warm-200 rounded-xl text-sm text-warm-400 text-center">
      No specific food guidance for this goal.
    </div>
  );

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* Recommend */}
        <div className="space-y-2">
          <p className="flex items-center gap-1.5 text-xs font-bold text-emerald-600 uppercase tracking-widest">
            <ThumbsUp className="h-3 w-3" /> Recommended Foods
          </p>
          {guide.recommend.length === 0 ? (
            <p className="text-xs text-warm-400 italic">Focus on maximizing intake of all foods.</p>
          ) : (
            <div className="space-y-1.5">
              {guide.recommend.map((food, i) => (
                <div key={i} className="flex items-center gap-2 px-3 py-2 border-l-2 border-emerald-400 bg-emerald-50 rounded-r-lg">
                  <p className="text-xs font-semibold text-warm-800">{food}</p>
                </div>
              ))}
            </div>
          )}
          <div className="mt-2 p-2.5 bg-warm-50 border border-warm-200 rounded-lg">
            <p className="flex items-center gap-1 text-xs font-bold text-warm-500 uppercase tracking-widest mb-1">
              <BookOpen className="h-2.5 w-2.5" /> General Recommendation
            </p>
            <p className="text-xs text-warm-600 leading-relaxed">{guide.generalRecommend}</p>
          </div>
        </div>

        {/* Avoid */}
        <div className="space-y-2">
          <p className="flex items-center gap-1.5 text-xs font-bold text-red-500 uppercase tracking-widest">
            <ThumbsDown className="h-3 w-3" /> Foods to Limit / Avoid
          </p>
          {guide.avoid.length === 0 ? (
            <p className="text-xs text-warm-400 italic">No strict food restrictions for this goal.</p>
          ) : (
            <div className="space-y-1.5">
              {guide.avoid.map((food, i) => (
                <div key={i} className="flex items-center gap-2 px-3 py-2 border-l-2 border-red-400 bg-red-50 rounded-r-lg">
                  <p className="text-xs font-semibold text-warm-800">{food}</p>
                </div>
              ))}
            </div>
          )}
          {guide.generalAvoid && (
            <div className="mt-2 p-2.5 bg-warm-50 border border-warm-200 rounded-lg">
              <p className="flex items-center gap-1 text-xs font-bold text-warm-500 uppercase tracking-widest mb-1">
                <BookOpen className="h-2.5 w-2.5" /> General Avoidance
              </p>
              <p className="text-xs text-warm-600 leading-relaxed">{guide.generalAvoid}</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
