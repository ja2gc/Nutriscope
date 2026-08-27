import { Tabs } from 'expo-router';
import { CalendarDays, ChefHat, ClipboardCheck, LayoutDashboard, Newspaper, ShoppingCart } from 'lucide-react-native';
import AppHeader from '../../components/AppHeader';
import { MOBILE_THEME } from '../../lib/theme';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

export default function TabsLayout() {
  const insets = useSafeAreaInsets();
  const tabHeight = 58 + insets.bottom;
  return (
    <Tabs
      screenOptions={{
        headerShown: true,
        tabBarActiveTintColor: MOBILE_THEME.colors.brand,
        tabBarInactiveTintColor: MOBILE_THEME.colors.muted,
        tabBarStyle: { backgroundColor: MOBILE_THEME.colors.surface, borderTopColor: MOBILE_THEME.colors.border, height: tabHeight, paddingTop: 6, paddingBottom: insets.bottom + 6 },
        tabBarLabelStyle: { fontSize: 9, fontWeight: '600' },
        tabBarHideOnKeyboard: true,
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Home',
          tabBarIcon: ({ color, size }) => (
            <LayoutDashboard color={color} size={size} />
          ),
          header: () => <AppHeader title="Dashboard" />,
        }}
      />
      <Tabs.Screen
        name="announcements"
        options={{
          title: 'Announcement',
          tabBarIcon: ({ color, size }) => (
            <Newspaper color={color} size={size} />
          ),
          header: () => <AppHeader title="Announcements & SOP" />,
        }}
      />
      <Tabs.Screen
        name="menu"
        options={{
          title: 'Menu',
          tabBarIcon: ({ color, size }) => (
            <CalendarDays color={color} size={size} />
          ),
          header: () => <AppHeader title="Menu Cycle" />,
        }}
      />
      <Tabs.Screen
        name="prep"
        options={{
          title: 'Meal Prep',
          tabBarIcon: ({ color, size }) => (
            <ChefHat color={color} size={size} />
          ),
          header: () => <AppHeader title="Meal Preparation" />,
        }}
      />
      <Tabs.Screen
        name="accomplishments"
        options={{
          title: 'Accomplish',
          tabBarIcon: ({ color, size }) => (
            <ClipboardCheck color={color} size={size} />
          ),
          header: () => <AppHeader title="Accomplishments" />,
        }}
      />
      <Tabs.Screen
        name="procurement"
        options={{
          title: 'Purchase',
          tabBarIcon: ({ color, size }) => (
            <ShoppingCart color={color} size={size} />
          ),
          header: () => <AppHeader title="Procurement" />,
        }}
      />
    </Tabs>
  );
}
