import { Text, View } from 'react-native';
import Svg, { Circle, Line, Path } from 'react-native-svg';

/**
 * NutriScope wordmark — leaf + diagnostic-scope crosshair, identical to the website
 * Logo (frontend/components/ui/Logo). Reused on login and in the app header so the
 * app and website read as one organization.
 */
export default function BrandLogo({ size = 28, showWordmark = true }: { size?: number; showWordmark?: boolean }) {
  return (
    <View className="flex-row items-center gap-2.5">
      <View className="relative items-center justify-center" style={{ height: size, width: size }}>
        <Svg style={{ width: size, height: size }} viewBox="0 0 32 32" fill="none">
          <Circle cx="16" cy="16" r="12" stroke="#ea580c" strokeWidth="1.5" strokeDasharray="4 2" opacity="0.75" />
          <Circle cx="16" cy="16" r="6" stroke="#ea580c" strokeWidth="1" opacity="0.40" />
          <Line x1="16" y1="2" x2="16" y2="6" stroke="#ea580c" strokeWidth="1.5" />
          <Line x1="16" y1="26" x2="16" y2="30" stroke="#ea580c" strokeWidth="1.5" />
          <Line x1="2" y1="16" x2="6" y2="16" stroke="#ea580c" strokeWidth="1.5" />
          <Line x1="26" y1="16" x2="30" y2="16" stroke="#ea580c" strokeWidth="1.5" />
          <Path d="M16 8C16 8 10 13 10 18C10 21.3137 12.6863 24 16 24C19.3137 24 22 21.3137 22 18C22 13 16 8 16 8Z" fill="#059669" />
          <Path d="M16 8C16 13 18.5 17 21 19.5" stroke="#d1fae5" strokeWidth="1" strokeLinecap="round" opacity="0.9" />
          <Path d="M16 24V14" stroke="#10b981" strokeWidth="1.2" strokeLinecap="round" />
        </Svg>
      </View>
      {showWordmark && (
        <View className="flex-row items-baseline">
          <Text className="text-base font-extrabold tracking-tight text-emerald-600">Nutri</Text>
          <Text className="text-base font-extrabold tracking-tight text-orange-600">Scope</Text>
        </View>
      )}
    </View>
  );
}
