# User vs. LegacyUser

This application has two user entity classes defined: A class User and a class LegacyUser which extends the User class. This is because of the different database handling in DBAL and Orm. The only difference between the LegacyUser class and his parent is that it supports custom fields, via an array. This makes it easier to extend it if you use DBAL whereas Orm can easily add fields.

# Contribution

Everyone is welcome to contribute to this project. The only thing you need to do is opening a pull request or an issue. By pushing code to the repository or doing pull requests, you accept that your code will be published under the GNU LGPL.